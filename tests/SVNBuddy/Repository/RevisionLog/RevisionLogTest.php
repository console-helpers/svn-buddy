<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Helper\ProgressBar;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\SimpleXMLElementToken;

class RevisionLogTest extends TestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	/**
	 * Console IO.
	 *
	 * @var ObjectProphecy
	 */
	protected $io;

	protected function setUp()
	{
		parent::setUp();

		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector');
		$this->io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please register at least one revision log plugin.
	 */
	public function testRefreshWithoutPlugins()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->refresh(false);
	}

	public function testPluginRegistrationSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();

		$this->assertFalse($revision_log->pluginRegistered('mocked'), 'The "mocked" plugin is not registered.');
		$revision_log->registerPlugin($plugin->reveal());
		$this->assertTrue($revision_log->pluginRegistered('mocked'), 'The "mocked" plugin is registered.');
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "mocked" revision log plugin is already registered.
	 */
	public function testPluginRegistrationFailure()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());
		$revision_log->registerPlugin($plugin->reveal());
	}

	public function testFindCriterionSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$plugin->find(array('criterion'), '/projects/project-name/')->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', 'criterion'));
	}

	public function testFindCriteriaSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$plugin->find(array('criterion1', 'criterion2'), '/projects/project-name/')->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', array('criterion1', 'criterion2')));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "mocked" revision log plugin is unknown.
	 */
	public function testFindFailure()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->find('mocked', '');
	}

	public function testGetRevisionsDataSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$plugin->getRevisionsData(array(1))->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->getRevisionsData('mocked', array(1)));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "mocked" revision log plugin is unknown.
	 */
	public function testGetRevisionsDataFailure()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->getRevisionsData('mocked', array(0));
	}

	public function testGetBugsFromRevisions()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('bugs')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$plugin->getRevisionsData(array(1, 2))
			->willReturn(array(
				1 => array('A', 'B'),
				2 => array('B', 'C'),
			))
			->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals(array('A', 'B', 'C'), $revision_log->getBugsFromRevisions(array(1, 2)));
	}

	/**
	 * @dataProvider refreshWithoutCacheWithOutputDataProvider
	 */
	public function testRefreshWithoutCacheWithOutput($is_verbose)
	{
		// Create progress bar for repository.
		$repository_progress_bar = $this->prophesize('Symfony\\Component\\Console\\Helper\\ProgressBar');
		$repository_progress_bar->setMessage(' * Reading missing revisions:')->shouldBeCalled();
		$repository_progress_bar
			->setFormat(
				'%message% %current%/%max% [%bar%] <info>%percent:3s%%</info> %elapsed:6s%/%estimated:-6s% <info>%memory:-10s%</info>'
			)
			->shouldBeCalled();
		$repository_progress_bar->start()->shouldBeCalled();
		$repository_progress_bar->advance()->shouldBeCalled();
		$repository_progress_bar->clear()->shouldBeCalled();

		$this->io->createProgressBar(3)->willReturn($repository_progress_bar)->shouldBeCalled();

		// Create progress bar for database.
		$database_progress_bar = $this->prophesize('Symfony\\Component\\Console\\Helper\\ProgressBar');
		$database_progress_bar->setMessage(' * Reading missing revisions:')->shouldBeCalled();
		$database_progress_bar
			->setFormat('%message% %current% [%bar%] %elapsed:6s% <info>%memory:-10s%</info>')
			->shouldBeCalled();
		$database_progress_bar->start()->shouldBeCalled();
		$database_progress_bar->advance()->shouldBeCalled();
		$database_progress_bar->finish()->shouldBeCalled();

		$this->io->createProgressBar()->willReturn($database_progress_bar)->shouldBeCalled();

		$this->io->writeln('')->shouldBeCalled();
		$this->io->isVerbose()->willReturn($is_verbose);

		if ( $is_verbose ) {
			$this->io->writeln('<debug>Combined Plugin Statistics:</debug>')->shouldBeCalled();
		}

		$this->testRefreshWithoutCacheWithoutOutput($this->io->reveal(), $database_progress_bar->reveal(), $is_verbose);
	}

	public function refreshWithoutCacheWithOutputDataProvider()
	{
		return array(
			'verbose' => array(true),
			'non-verbose' => array(false),
		);
	}

	public function testRefreshWithoutCacheWithoutOutput(ConsoleIO $io = null, ProgressBar $database_progress_bar = null, $is_verbose = false)
	{
		$this->repositoryConnector->getLastRevision('svn://localhost')->willReturn(400)->shouldBeCalled();

		// Create revision log (part 1).
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk', $io);

		// Add repository collector plugin.
		$repository_collector_plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IRepositoryCollectorPlugin');
		$repository_collector_plugin->getName()->willReturn('mocked_repo')->shouldBeCalled();
		$repository_collector_plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$repository_collector_plugin->whenDatabaseReady()->shouldBeCalled();
		$repository_collector_plugin->getLastRevision()->willReturn(0)->shouldBeCalled();

		$repository_collector_plugin->getRevisionQueryFlags()
			->willReturn(array(RevisionLog::FLAG_MERGE_HISTORY, RevisionLog::FLAG_VERBOSE))
			->shouldBeCalled();

		$repository_collector_plugin->parse(new SimpleXMLElementToken($this->expectSvnLogQuery(0, 199)))->shouldBeCalled();
		$repository_collector_plugin->parse(new SimpleXMLElementToken($this->expectSvnLogQuery(200, 399)))->shouldBeCalled();
		$repository_collector_plugin->parse(new SimpleXMLElementToken($this->expectSvnLogQuery(400, 400)))->shouldBeCalled();

		if ( $is_verbose ) {
			$repository_collector_plugin->getStatistics()->willReturn(array('rp1' => 10, 'rp2' => 20))->shouldBeCalled();
			$this->io->writeln('<debug> * rp1: 10</debug>')->shouldBeCalled();
			$this->io->writeln('<debug> * rp2: 20</debug>')->shouldBeCalled();
		}

		// Add database collector plugin.
		$database_collector_plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IDatabaseCollectorPlugin');
		$database_collector_plugin->getName()->willReturn('mocked_db')->shouldBeCalled();
		$database_collector_plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$database_collector_plugin->whenDatabaseReady()->shouldBeCalled();
		$database_collector_plugin->getLastRevision()->willReturn(0)->shouldBeCalled();
		$database_collector_plugin
			->process(0, 400, $database_progress_bar)
			->will(function (array $args) {
				if ( isset($args[2]) ) {
					$args[2]->advance();
				}
			})
			->shouldBeCalled();

		if ( $is_verbose ) {
			$database_collector_plugin->getStatistics()->willReturn(array('dp1' => 3, 'dp2' => 4))->shouldBeCalled();
			$this->io->writeln('<debug> * dp1: 3</debug>')->shouldBeCalled();
			$this->io->writeln('<debug> * dp2: 4</debug>')->shouldBeCalled();
		}

		// Create revision log (part 2).
		$revision_log->registerPlugin($repository_collector_plugin->reveal());
		$revision_log->registerPlugin($database_collector_plugin->reveal());
		$revision_log->refresh(false);
	}

	public function testRefreshWithCache()
	{
		$this->repositoryConnector->getLastRevision('svn://localhost')->willReturn(1000)->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		// Add repository collector plugin.
		$repository_collector_plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IRepositoryCollectorPlugin');
		$repository_collector_plugin->getName()->willReturn('mocked_repo')->shouldBeCalled();
		$repository_collector_plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$repository_collector_plugin->whenDatabaseReady()->shouldBeCalled();
		$repository_collector_plugin->getLastRevision()->willReturn(1000)->shouldBeCalled();

		// Add database collector plugin.
		$database_collector_plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IDatabaseCollectorPlugin');
		$database_collector_plugin->getName()->willReturn('mocked_db')->shouldBeCalled();
		$database_collector_plugin->setRevisionLog($revision_log)->shouldBeCalled();
		$database_collector_plugin->whenDatabaseReady()->shouldBeCalled();
		$database_collector_plugin->getLastRevision()->willReturn(1000)->shouldBeCalled();

		$revision_log->registerPlugin($repository_collector_plugin->reveal());
		$revision_log->registerPlugin($database_collector_plugin->reveal());
		$revision_log->refresh(false);
	}

	public function testGetProjectPath()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$this->assertEquals('/projects/project-name/', $revision_log->getProjectPath());
	}

	public function testGetRefName()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$this->assertEquals('trunk', $revision_log->getRefName());
	}

	/**
	 * Expects query to "svn log".
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   From revision.
	 *
	 * @return \SimpleXMLElement
	 */
	protected function expectSvnLogQuery($from_revision, $to_revision)
	{
		$svn_log_output = <<<OUTPUT
<?xml version="1.0"?>
<log>
<logentry
   revision="20128">
<author>alex</author>
<date>2015-10-13T13:30:16.473960Z</date>
<paths>
<path
   kind="file"
   action="M">/projects/project_a/trunk/sub-folder/file.tpl</path>
<path
   kind="file"
   action="M">/projects/project_a/trunk/sub-folder/file.php</path>
</paths>
<msg>#40846 - task title
1. task item</msg>
</logentry>
<logentry
   revision="20127">
<author>erik</author>
<date>2015-10-13T13:00:15.434252Z</date>
<paths>
<path
   kind="file"
   action="M">/projects/project_a/trunk/another_file.php</path>
</paths>
<msg>#40904 - task title
1) task item</msg>
</logentry>
</log>
OUTPUT;

		$this->expectRepositoryCommand(
			'log',
			'-r ' . $from_revision . ':' . $to_revision . ' --xml --verbose --use-merge-history {svn://localhost}',
			$svn_log_output
		);

		return new \SimpleXMLElement($svn_log_output);
	}

	/**
	 * Creates repository command expectation.
	 *
	 * @param string $command_name Command name.
	 * @param string $param_string Param string.
	 * @param mixed  $result       Result.
	 *
	 * @return void
	 */
	protected function expectRepositoryCommand($command_name, $param_string, $result)
	{
		if ( strpos($param_string, '--xml') !== false ) {
			$result = simplexml_load_string($result);
		}

		$command = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Command');
		$command->run()->willReturn($result)->shouldBeCalled();
		$command->setCacheDuration('10 years')->shouldBeCalled();

		$this->repositoryConnector->getCommand($command_name, $param_string)->willReturn($command)->shouldBeCalled();
	}

	/**
	 * Creates revision log.
	 *
	 * @param string    $repository_url Repository url.
	 * @param ConsoleIO $io             Console IO.
	 *
	 * @return RevisionLog
	 */
	protected function createRevisionLog($repository_url, ConsoleIO $io = null)
	{
		$this->repositoryConnector->getRootUrl($repository_url)->willReturn('svn://localhost')->shouldBeCalled();
		$this->repositoryConnector->getRelativePath($repository_url)->willReturn('/projects/project-name/trunk')->shouldBeCalled();
		$this->repositoryConnector->getProjectUrl('/projects/project-name/trunk')->willReturn('/projects/project-name')->shouldBeCalled();
		$this->repositoryConnector->getRefByPath('/projects/project-name/trunk')->willReturn('trunk')->shouldBeCalled();

		$revision_log = new RevisionLog(
			$repository_url,
			$this->repositoryConnector->reveal(),
			$io
		);

		return $revision_log;
	}

}
