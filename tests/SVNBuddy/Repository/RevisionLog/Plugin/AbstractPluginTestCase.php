<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin;


use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RepositoryFiller;
use Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog\AbstractDatabaseAwareTestCase;
use Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog\CommitBuilder;

abstract class AbstractPluginTestCase extends AbstractDatabaseAwareTestCase
{

	/**
	 * Revision log plugin.
	 *
	 * @var IPlugin
	 */
	protected $plugin;

	/**
	 * Repository filler.
	 *
	 * @var RepositoryFiller
	 */
	protected $filler;

	/**
	 * Commit builder.
	 *
	 * @var CommitBuilder
	 */
	protected $commitBuilder;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		parent::setupTest();

		if ( strpos($this->getTestName(), 'testProcess') === 0 ) {
			$this->database->setProfiler($this->createStatementProfiler());
		}

		$database_cache = new DatabaseCache($this->database);
		$this->filler = new RepositoryFiller($this->database, $database_cache);
		$this->commitBuilder = new CommitBuilder($this->filler, $database_cache);

		$this->plugin = $this->createPlugin();
	}

	public function testSetOverwriteMode()
	{
		if ( !($this->plugin instanceof IOverwriteAwarePlugin) ) {
			$this->markTestSkipped(
				'The "' . $this->plugin->getName() . '" plugin doesn\'t support an overwrite mode.'
			);
		}

		$this->assertFalse($this->plugin->isOverwriteMode());
		$this->plugin->setOverwriteMode(true);
		$this->assertTrue($this->plugin->isOverwriteMode());
	}

	public function testGetLastRevisionEmpty()
	{
		$this->assertEquals(0, $this->plugin->getLastRevision());
	}

	public function testGetLastRevisionNonEmpty()
	{
		$sql = 'INSERT INTO PluginData (Name, LastRevision)
				VALUES (:name, :revision)';
		$this->database->perform($sql, array(
			'name' => $this->plugin->getName(),
			'revision' => 5,
		));

		$this->assertEquals(5, $this->plugin->getLastRevision());
	}

	public function testFindNonExistingProject()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The project with "/path/to/project/" path not found.');

		$this->plugin->find(array('anything'), '/path/to/project/');
	}

	/**
	 * Sets last revision processed by plugin.
	 *
	 * @param integer $last_revision Last revision.
	 *
	 * @return void
	 */
	protected function setLastRevision($last_revision)
	{
		$sql = 'REPLACE INTO PluginData (Name, LastRevision)
				VALUES (:name, :last_revision)';
		$this->database->perform($sql, array('name' => $this->plugin->getName(), 'last_revision' => $last_revision));
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	abstract protected function createPlugin();

	/**
	 * Checks, that last parsed revision of plugin matches desired one.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return void
	 */
	protected function assertLastRevision($revision)
	{
		$sql = 'SELECT LastRevision
				FROM PluginData
				WHERE Name = :name';
		$last_revision = $this->database->fetchValue($sql, array('name' => $this->plugin->getName()));

		$this->assertEquals($revision, $last_revision, 'Plugin last parsed revision is recorded');
	}

	/**
	 * Confirms, that statistics was collected correctly.
	 *
	 * @param array $expected_statistics Expected statistics.
	 *
	 * @return void
	 */
	protected function assertStatistics(array $expected_statistics)
	{
		$this->assertEquals(
			$expected_statistics,
			array_filter($this->plugin->getStatistics()),
			'Plugin collected statistics matches expected one.'
		);
	}

	/**
	 * Returns fixture by name.
	 *
	 * @param string $name Fixture name.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When fixture wasn't found.
	 */
	protected function getFixture($name)
	{
		$fixture_filename = __DIR__ . '/fixtures/' . $this->plugin->getName() . '/' . $name;

		if ( !file_exists($fixture_filename) ) {
			throw new \InvalidArgumentException('The "' . $name . '" fixture does not exist.');
		}

		return new \SimpleXMLElement(file_get_contents($fixture_filename));
	}

}
