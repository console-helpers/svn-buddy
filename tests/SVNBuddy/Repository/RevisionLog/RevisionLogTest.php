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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use ConsoleHelpers\SVNBuddy\Repository\RevisionUrlBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\SimpleXMLElementToken;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class RevisionLogTest extends TestCase
{

	use ExpectException;

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

	/**
	 * Revision URL builder.
	 *
	 * @var ObjectProphecy|RevisionUrlBuilder
	 */
	protected $revisionUrlBuilder;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector');
		$this->io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
		$this->revisionUrlBuilder = $this->prophesize(RevisionUrlBuilder::class);
	}

	public function testRefreshWithoutPlugins()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('Please register at least one revision log plugin.');

		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->refresh(false);
	}

	public function testPluginRegistrationSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$plugin = $this->createPluginMock($revision_log);

		$this->assertFalse($revision_log->pluginRegistered('mocked'), 'The "mocked" plugin is not registered.');
		$revision_log->registerPlugin($plugin->reveal());
		$this->assertTrue($revision_log->pluginRegistered('mocked'), 'The "mocked" plugin is registered.');
	}

	public function testPluginRegistrationFailure()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The "mocked" revision log plugin is already registered.');

		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$plugin = $this->createPluginMock($revision_log);

		$revision_log->registerPlugin($plugin->reveal());
		$revision_log->registerPlugin($plugin->reveal());
	}

	public function testGetPluginSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->createPluginMock($revision_log);
		$revision_log->registerPlugin($plugin->reveal());

		$this->assertSame($plugin->reveal(), $revision_log->getPlugin('mocked'));
	}

	public function testGetPluginFailure()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "mocked" revision log plugin is unknown.');

		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->getPlugin('mocked');
	}

	public function testFindCriterionSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->createPluginMock($revision_log);
		$plugin->find(array('criterion'), '/projects/project-name/')->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', 'criterion'));
	}

	public function testFindCriteriaSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->createPluginMock($revision_log);
		$plugin->find(array('criterion1', 'criterion2'), '/projects/project-name/')->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', array('criterion1', 'criterion2')));
	}

	public function testFindFailure()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "mocked" revision log plugin is unknown.');

		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');
		$revision_log->find('mocked', '');
	}

	public function testGetRevisionsDataSuccess()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$plugin = $this->createPluginMock($revision_log);
		$plugin->getRevisionsData(array(1))->willReturn('OK')->shouldBeCalled();

		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->getRevisionsData('mocked', array(1)));
	}

	public function testGetRevisionsDataFailure()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "mocked" revision log plugin is unknown.');

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
		$output_formatter = $this->prophesize(OutputFormatterInterface::class);
		$output_formatter->format(Argument::any())->willReturnArgument();
		$output_formatter->isDecorated()->willReturn(false);
		$output_formatter->setDecorated(false)->shouldBeCalled();

		$output = $this->prophesize(OutputInterface::class);
		$output->isDecorated()->willReturn(false);
		$output->getVerbosity()->willReturn(
			$is_verbose ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL
		);
		$output->getFormatter()->willReturn($output_formatter->reveal());

		// Create progress bar for repository.
		$repository_progress_bar = new ProgressBar($output->reveal(), 3);
		$this->expectProgressBarOutput(
			$output,
			array(
				' * Reading missing revisions: 0/3 [>---------------------------] <info>  0%</info> < 1 sec/< 1 sec <info>8.0 MiB   </info>',
				"\n * Reading missing revisions: 1/3 [=========>------------------] <info> 33%</info> < 1 sec/< 1 sec <info>8.0 MiB   </info>",
				"\n * Reading missing revisions: 2/3 [==================>---------] <info> 66%</info> < 1 sec/< 1 sec <info>8.0 MiB   </info>",
				"\n * Reading missing revisions: 3/3 [============================] <info>100%</info> < 1 sec/< 1 sec <info>8.0 MiB   </info>",
			)
		);
		$this->io->createProgressBar(3)->willReturn($repository_progress_bar)->shouldBeCalled();

		// Create progress bar for database.
		$database_progress_bar = new ProgressBar($output->reveal());
		$this->expectProgressBarOutput(
			$output,
			array(
				' * Reading missing revisions:    0 [>---------------------------] < 1 sec <info>8.0 MiB   </info>',
				"\n * Reading missing revisions:    1 [->--------------------------] < 1 sec <info>8.0 MiB   </info>",
			)
		);
		$this->io->createProgressBar()->willReturn($database_progress_bar)->shouldBeCalled();

		$this->io->writeln('')->shouldBeCalled();
		$this->io->isVerbose()->willReturn($is_verbose);

		if ( $is_verbose ) {
			$this->io->writeln('<debug>Combined Plugin Statistics:</debug>')->shouldBeCalled();
		}

		$this->testRefreshWithoutCacheWithoutOutput($this->io->reveal(), $database_progress_bar, $is_verbose);
	}

	/**
	 * Expects approximate writes.
	 *
	 * @param ObjectProphecy|OutputInterface $output Output.
	 * @param array                          $lines  Lines.
	 *
	 * @return void
	 */
	protected function expectProgressBarOutput(ObjectProphecy $output, array $lines)
	{
		foreach ( $lines as $expected_line ) {
			$expected_line = preg_replace('#<info>\d+\.\d+ MiB\s+</info>#', '<info>0.0 MiB</info>', $expected_line);
			$expected_line = preg_replace('/(<)?\s+\d+(\.\d+)? sec(s)?/', '5 min', $expected_line);

			$output->write(
				Argument::that(function ($actual_line) use ($expected_line) {
					$actual_line = preg_replace('#<info>\d+\.\d+ MiB\s+</info>#', '<info>0.0 MiB</info>', $actual_line);
					$actual_line = preg_replace('/(<)?\s+\d+(\.\d+)? sec(s)?/', '5 min', $actual_line);

					return $actual_line === $expected_line;
				})
			)->shouldBeCalled();
		}
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

	public function testGetRevisionUrlBuilder()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/projects/project-name/trunk');

		$this->assertSame($this->revisionUrlBuilder->reveal(), $revision_log->getRevisionURLBuilder());
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
			$this->revisionUrlBuilder->reveal(),
			$this->repositoryConnector->reveal(),
			$io
		);

		return $revision_log;
	}

	/**
	 * Returns plugin mock.
	 *
	 * @param RevisionLog $revision_log Revision log.
	 *
	 * @return ObjectProphecy
	 */
	protected function createPluginMock(RevisionLog $revision_log)
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\Plugin\\IPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->setRevisionLog($revision_log)->shouldBeCalled();

		return $plugin;
	}

}
