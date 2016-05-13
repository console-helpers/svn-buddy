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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\SummaryPlugin;

class SummaryPluginTest extends AbstractPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('summary', $this->plugin->getName());
	}

	public function testGetRevisionQueryFlags()
	{
		$this->assertEmpty(
			$this->plugin->getRevisionQueryFlags()
		);
	}

	public function testParseIgnoreExisting()
	{
		$this->setLastRevision(500);

		$this->plugin->parse($this->getFixture('svn_log_summary_non_verbose.xml'));

		$this->assertLastRevision(500);
		$this->assertTableEmpty('Commits');
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
			'Commits',
			array(
				array(
					'Revision' => '100',
					'Author' => 'user',
					'Date' => '1461350740',
					'Message' => 'message',
				),
			)
		);

		$this->assertStatistics(array(
			SummaryPlugin::STATISTIC_COMMIT_ADDED => 1,
		));
	}

	public function parseDataProvider()
	{
		return array(
			'non-verbose' => array('svn_log_summary_non_verbose.xml'),
			'verbose' => array('svn_log_summary_verbose.xml'),
		);
	}

	public function testFindNoMatch()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'task title')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');
		$this->commitBuilder->build();

		$this->assertEmpty($this->plugin->find(array('author:alex'), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$this->markTestIncomplete('Until multi-field search is implemented this can\'t be tested');
	}

	public function testFindSorting()
	{
		$this->commitBuilder
			->addCommit(100, 'user1', 0, 'task title')
			->addPath('A', '/path/to/project/file.txt', '', '/path/to/project/');
		$this->commitBuilder
			->addCommit(200, 'user2', 0, 'task title')
			->addPath('M', '/path/to/project/file.txt', '', '/path/to/project/');
		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(
				array(
					'author:user2',
					'author:user1',
				),
				'/path/to/project/'
			)
		);

		// Confirm search is bound to project.
		$this->commitBuilder
			->addCommit(300, 'user', 0, 'task title')
			->addPath('A', '/path/to/no-match-project/file.txt', '', '/path/to/no-match-project/');
		$this->commitBuilder->build();

		$this->assertEmpty(
			$this->plugin->find(
				array(
					'author:user2',
					'author:user1',
				),
				'/path/to/no-match-project/'
			)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Each criterion of "summary" plugin must be in "field:value" format.
	 */
	public function testFindMalformedCriterion()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'task title')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');
		$this->commitBuilder->build();

		$this->plugin->find(array('keyword'), '/path/to/project/');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Searching by "field" is not supported by "summary" plugin.
	 */
	public function testFindUnsupportedField()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'task title')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');
		$this->commitBuilder->build();

		$this->plugin->find(array('field:keyword'), '/path/to/project/');
	}

	public function testGetRevisionsDataSuccess()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'task title');
		$this->commitBuilder->build();

		$this->assertEquals(
			array(
				100 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			$this->plugin->getRevisionsData(array(100))
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Revision(-s) "100" not found by "summary" plugin.
	 */
	public function testGetRevisionsDataFailure()
	{
		$this->plugin->getRevisionsData(array(100));
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new SummaryPlugin($this->database, $this->filler);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
