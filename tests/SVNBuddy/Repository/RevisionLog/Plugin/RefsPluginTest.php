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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RefsPlugin;
use Prophecy\Prophecy\ObjectProphecy;

class RefsPluginTest extends AbstractPluginTestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	protected function setUp()
	{
		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		parent::setUp();
	}

	public function testGetName()
	{
		$this->assertEquals('refs', $this->plugin->getName());
	}

	public function testProcess()
	{
		$this->plugin->process(0, 100);
		$this->assertLastRevision(100);
	}

	public function testFindNoMatch()
	{
		$this->createFixture();

		$this->assertEmpty($this->plugin->find(array('branches/new-branch'), '/path/to/project-a/'));

		// Confirm search is bound to project.
		$this->assertEmpty($this->plugin->find(array('tags/tag-name'), '/path/to/project-b/'));
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$this->createFixture();

		$this->assertEquals(
			array(100),
			$this->plugin->find(array('branches/branch-name', 'tags/tag-name'), '/path/to/project-a/')
		);
	}

	public function testFindAllRefs()
	{
		$this->createFixture();

		$this->assertEquals(
			array('branches/branch-name', 'tags/tag-name'),
			$this->plugin->find(array('all_refs'), '/path/to/project-a/')
		);
	}

	public function testFindSorting()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/branches/branch-name/', 'branches/branch-name', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('A', '/path/to/project-a/tags/tag-name/', 'tags/tag-name', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(300, 'user', 0, '')
			->addPath('A', '/path/to/project-b/branches/branch-name/', 'branches/branch-name', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(
				array(
					'tags/tag-name',
					'branches/branch-name',
				),
				'/path/to/project-a/'
			)
		);
	}

	public function testGetRevisionsData()
	{
		$this->createFixture();

		$this->assertEquals(
			array(
				100 => array('branches/branch-name', 'tags/tag-name'),
				105 => array(),
			),
			$this->plugin->getRevisionsData(array(100, 105))
		);
	}

	/**
	 * Creates fixture.
	 *
	 * @return void
	 */
	protected function createFixture()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/branches/branch-name/', 'branches/branch-name', '/path/to/project-a/')
			->addPath('A', '/path/to/project-a/tags/tag-name/', 'tags/tag-name', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('A', '/path/to/project-b/branches/branch-name/', 'branches/branch-name', '/path/to/project-b/');

		$this->commitBuilder->build();
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new RefsPlugin($this->database, $this->filler);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
