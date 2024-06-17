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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RepositoryCollectorPlugin\MergesPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;

class MergesPluginTest extends AbstractPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('merges', $this->plugin->getName());
	}

	public function testGetRevisionQueryFlags()
	{
		$this->assertEquals(
			array(RevisionLog::FLAG_MERGE_HISTORY),
			$this->plugin->getRevisionQueryFlags()
		);
	}

	public function testParseIgnoreExisting()
	{
		$this->setLastRevision(500);
		$this->plugin->parse($this->getFixture('svn_log_merge_non_verbose.xml'));
		$this->assertLastRevision(500);

		$this->assertTableEmpty('Merges');
		$this->assertStatistics(array());
	}

	/**
	 * @dataProvider parseDataProvider
	 */
	public function testParse($fixture_file)
	{
		$this->plugin->parse($this->getFixture($fixture_file));
		$this->assertLastRevision(100);

		$this->assertTableContent(
			'Merges',
			array(
				array(
					'MergeRevision' => '100',
					'MergedRevision' => '50',
				),
				array(
					'MergeRevision' => '100',
					'MergedRevision' => '60',
				),
			)
		);

		$this->assertStatistics(array(
			MergesPlugin::STATISTIC_MERGE_ADDED => 2,
		));
	}

	/**
	 * @dataProvider parseDataProvider
	 */
	public function testParseWithOverwriteMode($fixture_file)
	{
		$this->commitBuilder
			->addCommit(100, 'user', 123, 'msg')
			->addMergedCommits(array(10, 20, 30));
		$this->commitBuilder->build();
		$this->setLastRevision(100);

		$this->plugin->setOverwriteMode(true);
		$this->plugin->parse($this->getFixture($fixture_file));

		$this->assertTableContent(
			'Merges',
			array(
				array(
					'MergeRevision' => '100',
					'MergedRevision' => '50',
				),
				array(
					'MergeRevision' => '100',
					'MergedRevision' => '60',
				),
			)
		);

		$this->assertStatistics(array(
			MergesPlugin::STATISTIC_MERGE_DELETED => 3,
			MergesPlugin::STATISTIC_MERGE_ADDED => 2,
		));
	}

	public static function parseDataProvider()
	{
		return array(
			'verbose' => array('svn_log_merge_non_verbose.xml'),
			'non-verbose' => array('svn_log_merge_verbose.xml'),
		);
	}

	public function testFindNoMatch()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The merge revision(-s) "105" not found.');

		$this->createFixture();

		$this->plugin->find(array(105), '/path/to/project/');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$this->createFixture();

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array(100, 200), '/path/to/project/')
		);

		// Confirm search is bound to project.
		try {
			$this->assertEmpty($this->plugin->find(array(100, 200), '/path/to/no-match-project/'));
		}
		catch ( \InvalidArgumentException $e ) {
			$this->assertEquals('The merge revision(-s) "100", "200" not found.', $e->getMessage());
		}
	}

	public function testFindSorting()
	{
		$this->createFixture();

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array(200, 100), '/path/to/project/')
		);

		// Confirm search is bound to project.
		try {
			$this->assertEmpty($this->plugin->find(array(200, 100), '/path/to/no-match-project/'));
		}
		catch ( \InvalidArgumentException $e ) {
			$this->assertEquals('The merge revision(-s) "200", "100" not found.', $e->getMessage());
		}
	}

	public function testFindAllMerges()
	{
		$this->createFixture();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array('all_merges'), '/path/to/project/')
		);

		// Confirm search is bound to project.
		$this->assertEmpty($this->plugin->find(array('all_merges'), '/path/to/no-match-project/'));
	}

	public function testFindAllMerged()
	{
		$this->createFixture();

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array('all_merged'), '/path/to/project/')
		);

		// Confirm search is bound to project.
		$this->assertEmpty($this->plugin->find(array('all_merged'), '/path/to/no-match-project/'));
	}

	public function testGetRevisionsData()
	{
		$this->createFixture();

		$this->assertEquals(
			array(
				60 => array(100, 200),
				70 => array(),
			),
			$this->plugin->getRevisionsData(array(60, 70))
		);
	}

	/**
	 * Creates fixture for testing.
	 *
	 * @return void
	 */
	protected function createFixture()
	{
		$this->commitBuilder
			->addCommit(50, 'user', 0, 'merge-me-1')
			->addPath('A', '/path/to/project/trunk/file1.txt', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(60, 'user', 0, 'merge-me-2')
			->addPath('A', '/path/to/project/trunk/file2.txt', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(65, 'user', 0, 'merge-me-2')
			->addPath('A', '/path/to/project/trunk/file3.txt', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(100, 'user', 0, 'merge')
			->addPath('A', '/path/to/project/tags/stable/file1.txt', 'tags/stable', '/path/to/project/')
			->addPath('A', '/path/to/project/tags/stable/file2.txt', 'tags/stable', '/path/to/project/')
			->addMergedCommits(array(50, 60));

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'merge')
			->addPath('A', '/path/to/project/tags/stable/file2.txt', 'tags/stable', '/path/to/project/')
			->addPath('A', '/path/to/project/tags/stable/file3.txt', 'tags/stable', '/path/to/project/')
			->addMergedCommits(array(60, 65));

		$this->commitBuilder->build();

		// For checking, that search happens inside the project.
		$this->commitBuilder
			->addCommit(70, 'user', 0, 'merge-me-1')
			->addPath('A', '/path/to/no-match-project/trunk/file1.txt', 'trunk', '/path/to/no-match-project/');

		$this->commitBuilder->build();
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new MergesPlugin($this->database, $this->filler);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
