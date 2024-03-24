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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\BugsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;

class BugsPluginTest extends AbstractPluginTestCase
{

	/**
	 * Log message parser.
	 *
	 * @var ObjectProphecy
	 */
	protected $logMessageParserFactory;

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->repositoryConnector = $this->prophesize(Connector::class);
		$this->logMessageParserFactory = $this->prophesize(LogMessageParserFactory::class);

		parent::setupTest();
	}

	public function testGetName()
	{
		$this->assertEquals('bugs', $this->plugin->getName());
	}

	public function testProcessLastRevisionUpdated()
	{
		$this->plugin->process(0, 100);
		$this->assertLastRevision(100);

		$this->assertTableEmpty('CommitBugs');
	}

	public function testProcessEmptyProject()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder->build();

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->plugin->process(0, 100);

		$this->assertTableEmpty('CommitBugs');
		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => '',
					'IsDeleted' => '0',
				),
			)
		);
	}

	public function testProcessProjectWithUnknownStructure()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/sub-folder/', '', '/path/to/project/');

		$this->commitBuilder->build();

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->plugin->process(0, 100);

		$this->assertTableEmpty('CommitBugs');
		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => '',
					'IsDeleted' => '0',
				),
			)
		);
	}

	/**
	 * @dataProvider processDetectsMissingBugRegexpsDataProvider
	 */
	public function testProcessDetectsMissingBugRegexps($project_deleted)
	{
		$this->repositoryConnector->isRefRoot('/path/to/project/trunk/')->willReturn(true)->shouldBeCalled();
		$this->repositoryConnector->isRefRoot('/path/to/project/branches/branch-name/')->willReturn(true)->shouldBeCalled();
		$this->repositoryConnector->isRefRoot(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->repositoryConnector->withCache('1 year', false)->willReturn($this->repositoryConnector);

		$this->setBugRegexpExpectation($project_deleted, 'OK');

		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/', 'trunk', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/file.txt', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'message')
			->addPath('A', '/path/to/project/branches/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/branches/branch-name/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/branches/branch-name/file.txt', '', '/path/to/project/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1".
		$this->filler->setProjectStatus(1, $project_deleted);

		$this->setLastRevision(200);
		$this->plugin->process(0, 200);

		$this->assertTableEmpty('CommitBugs');
		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => 'OK',
					'IsDeleted' => $project_deleted,
				),
			)
		);
	}

	/**
	 * @dataProvider processDetectsMissingBugRegexpsDataProvider
	 */
	public function testBugRegexpsRefresh($project_deleted)
	{
		$this->repositoryConnector->isRefRoot('/path/to/project/trunk/')->willReturn(true)->shouldBeCalled();
		$this->repositoryConnector->isRefRoot('/path/to/project/branches/branch-name/')->willReturn(true)->shouldBeCalled();
		$this->repositoryConnector->isRefRoot(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->repositoryConnector->withCache('1 year', false)->willReturn($this->repositoryConnector)->shouldBeCalled();

		$this->setBugRegexpExpectation($project_deleted, 'FIRST_EXPRESSION');

		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/', 'trunk', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/file.txt', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'message')
			->addPath('A', '/path/to/project/branches/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/branches/branch-name/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/branches/branch-name/file.txt', '', '/path/to/project/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1".
		$this->filler->setProjectStatus(1, $project_deleted);

		$this->setLastRevision(200);
		$this->plugin->process(0, 200);

		$this->assertTableEmpty('CommitBugs');
		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => 'FIRST_EXPRESSION',
					'IsDeleted' => $project_deleted,
				),
			)
		);

		$this->setBugRegexpExpectation($project_deleted, 'SECOND_EXPRESSION');

		$this->repositoryConnector->withCache('1 year', true)->willReturn($this->repositoryConnector)->shouldBeCalled();

		$this->plugin->refreshBugRegExp('/path/to/project/');

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => 'SECOND_EXPRESSION',
					'IsDeleted' => $project_deleted,
				),
			)
		);
	}

	/**
	 * Sets bug regexp expectation.
	 *
	 * @param boolean $project_deleted Is project deleted.
	 * @param string  $expression      Expression.
	 *
	 * @return void
	 */
	public function setBugRegexpExpectation($project_deleted, $expression)
	{
		if ( $project_deleted ) {
			$this->repositoryConnector
				->getProperty('bugtraq:logregex', 'svn://localhost/path/to/project/branches/branch-name/@200')
				->willReturn($expression)
				->shouldBeCalled();
		}
		else {
			$this->repositoryConnector
				->getProperty('bugtraq:logregex', 'svn://localhost/path/to/project/branches/branch-name/')
				->willReturn($expression)
				->shouldBeCalled();
		}
	}

	public static function processDetectsMissingBugRegexpsDataProvider()
	{
		return array(
			'project deleted' => array('0'),
			'project not deleted' => array('1'),
		);
	}

	public function testProcessCommitWithoutBugs()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/', 'trunk', '/path/to/project/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1".
		$this->filler->setProjectBugRegexp(1, 'OK');

		$log_message_parser = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser');
		$log_message_parser->parse('message')->willReturn(array())->shouldBeCalled();
		$this->logMessageParserFactory->getLogMessageParser('OK')->willReturn($log_message_parser);

		$this->plugin->process(0, 100);

		$this->assertTableEmpty('CommitBugs');
	}

	public function testProcessCommitWithTwoProjects()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-one/', '', '/path/to/project-one/')
			->addPath('A', '/path/to/project-two/', '', '/path/to/project-two/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'message')
			->addPath('A', '/path/to/project-one/trunk/', 'trunk', '/path/to/project-one/')
			->addPath('A', '/path/to/project-two/trunk/', 'trunk', '/path/to/project-two/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1" and "2".
		$this->filler->setProjectBugRegexp(1, 'OK');
		$this->filler->setProjectBugRegexp(2, 'OK');

		$log_message_parser = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser');
		$log_message_parser->parse('message')->willReturn(array('BUG1', 'BUG2'))->shouldBeCalled();
		$log_message_parser->parse(Argument::any())->willReturn(array())->shouldBeCalled();
		$this->logMessageParserFactory->getLogMessageParser('OK')->willReturn($log_message_parser);

		$this->plugin->process(0, 200);

		$this->assertTableContent(
			'CommitBugs',
			array(
				array(
					'Revision' => '200',
					'Bug' => 'BUG1',
				),
				array(
					'Revision' => '200',
					'Bug' => 'BUG2',
				),
			)
		);
	}

	public function testProcessCommitFromProjectWithoutBugTracking()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message1')
			->addPath('A', '/path/to/project-one/', '', '/path/to/project-one/')
			->addPath('A', '/path/to/project-one/trunk/', 'trunk', '/path/to/project-one/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'message2')
			->addPath('A', '/path/to/project-two/', '', '/path/to/project-two/')
			->addPath('A', '/path/to/project-two/trunk/', 'trunk', '/path/to/project-two/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1" and "2".
		$this->filler->setProjectBugRegexp(1, 'OK');
		$this->filler->setProjectBugRegexp(2, '');

		$log_message_parser = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser');
		$log_message_parser->parse('message1')->willReturn(array('BUG1', 'BUG2'))->shouldBeCalled();
		$this->logMessageParserFactory->getLogMessageParser('OK')->willReturn($log_message_parser);

		$this->plugin->process(0, 200);

		$this->assertTableContent(
			'CommitBugs',
			array(
				array(
					'Revision' => '100',
					'Bug' => 'BUG1',
				),
				array(
					'Revision' => '100',
					'Bug' => 'BUG2',
				),
			)
		);
	}

	public function testProcessMultipleCommitsSameBug()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/', 'trunk', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'message')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addPath('A', '/path/to/project/trunk/', 'trunk', '/path/to/project/');

		$this->commitBuilder->build();

		// Assuming that project id would be "1" and "2".
		$this->filler->setProjectBugRegexp(1, 'OK');
		$this->filler->setProjectBugRegexp(2, '');

		$log_message_parser = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser');
		$log_message_parser->parse('message')->willReturn(array('BUG1'))->shouldBeCalled();
		$this->logMessageParserFactory->getLogMessageParser('OK')->willReturn($log_message_parser);

		$this->plugin->process(0, 200);

		$this->assertTableContent(
			'CommitBugs',
			array(
				array(
					'Revision' => '100',
					'Bug' => 'BUG1',
				),
				array(
					'Revision' => '200',
					'Bug' => 'BUG1',
				),
			)
		);
	}

	public function testFindNoMatch()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project/', '', '/path/to/project/')
			->addBugs(array('JRA-1'));
		$this->commitBuilder->build();

		$this->assertEmpty(
			$this->plugin->find(array('JRA-6'), '/path/to/project/'),
			'No revisions were found.'
		);
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty(
			$this->plugin->find(array(), '/path/to/project/'),
			'No revisions were found.'
		);
	}

	public function testFindNoDuplicates()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addBugs(array('JRA-1', 'JRA-2'));

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('A', '/path/to/project-b/', '', '/path/to/project-b/')
			->addBugs(array('JRA-1', 'JRA-2'));

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100),
			$this->plugin->find(array('JRA-1', 'JRA-2'), '/path/to/project-a/')
		);
	}

	public function testFindSorting()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addBugs(array('JRA-1'));

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addBugs(array('JRA-2'));

		$this->commitBuilder
			->addCommit(300, 'user', 0, '')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-b/')
			->addBugs(array('JRA-2'));

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array('JRA-2', 'JRA-1'), '/path/to/project-a/')
		);
	}

	public function testGetRevisionsData()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addBugs(array('JRA-1', 'JRA-2'));

		$this->commitBuilder->build();

		$this->assertEquals(
			array(
				100 => array('JRA-1', 'JRA-2'),
				50 => array(),
			),
			$this->plugin->getRevisionsData(array(100, 50))
		);
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new BugsPlugin(
			$this->database,
			$this->filler,
			'svn://localhost',
			$this->repositoryConnector->reveal(),
			$this->logMessageParserFactory->reveal()
		);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
