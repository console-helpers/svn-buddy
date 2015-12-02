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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\RegExToken;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\SimpleXMLElementToken;

class RevisionLogTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	/**
	 * Cache manager.
	 *
	 * @var ObjectProphecy
	 */
	protected $cacheManager;

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
		$this->cacheManager = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager');
		$this->io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please register at least one revision log plugin.
	 */
	public function testRefreshWithoutPlugins()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->refresh();
	}

	public function testPluginRegistrationSuccess()
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');

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
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');

		$revision_log->registerPlugin($plugin->reveal());
		$revision_log->registerPlugin($plugin->reveal());
	}

	public function testFindCriterionSuccess()
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->find(array('criterion'))->willReturn('OK')->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', 'criterion'));
	}

	public function testFindCriteriaSuccess()
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->find(array('criterion1', 'criterion2'))->willReturn('OK')->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->find('mocked', array('criterion1', 'criterion2')));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "mocked" revision log plugin is unknown.
	 */
	public function testFindFailure()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->find('mocked', '');
	}

	public function testGetRevisionDataSuccess()
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->getRevisionData(1)->willReturn('OK')->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals('OK', $revision_log->getRevisionData('mocked', 1));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "mocked" revision log plugin is unknown.
	 */
	public function testGetRevisionDataFailure()
	{
		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->getRevisionData('mocked', 0);
	}

	public function testGetBugsFromRevisions()
	{
		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('bugs')->shouldBeCalled();
		$plugin->getRevisionData(1)->willReturn(array('A', 'B'))->shouldBeCalled();
		$plugin->getRevisionData(2)->willReturn(array('B', 'C'))->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->registerPlugin($plugin->reveal());

		$this->assertEquals(array('A', 'B', 'C'), $revision_log->getBugsFromRevisions(array(1, 2)));
	}

	public function testRefreshWithoutCache()
	{
		$new_collected_data = array(
			'mocked' => array('NEW_COLLECTED'),
		);
		$cache_invalidator = new RegExToken('/^main:[\d]+;plugin\(mocked\):[\d]+$/');

		$this->repositoryConnector->getFirstRevision('svn://localhost')->willReturn(1000)->shouldBeCalled();
		$this->repositoryConnector->getLastRevision('svn://localhost')->willReturn(3000)->shouldBeCalled();
		$this->repositoryConnector->getProjectUrl('svn://localhost/trunk')->willReturn('svn://localhost')->shouldBeCalled();

		$this->cacheManager
			->getCache('log:svn://localhost', $cache_invalidator)
			->shouldBeCalled();
		$this->cacheManager
			->setCache('log:svn://localhost', $new_collected_data, $cache_invalidator)
			->shouldBeCalled();

		$progress_bar = $this->prophesize('Symfony\\Component\\Console\\Helper\\ProgressBar');
		$progress_bar
			->setFormat(
				' * Reading missing revisions: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%'
			)
			->shouldBeCalled();
		$progress_bar->start()->shouldBeCalled();
		$progress_bar->advance()->shouldBeCalled();
		$progress_bar->finish()->shouldBeCalled();

		$this->io->createProgressBar(2)->willReturn($progress_bar)->shouldBeCalled();
		$this->io->writeln('')->shouldBeCalled();

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->getCacheInvalidator()->willReturn(5)->shouldBeCalled();

		$plugin->getLastRevision()->shouldBeCalled();
		$plugin->parse(new SimpleXMLElementToken($this->expectSvnLogQuery(1000, 2000)))->shouldBeCalled();
		$plugin->parse(new SimpleXMLElementToken($this->expectSvnLogQuery(2001, 3000)))->shouldBeCalled();
		$plugin->getCollectedData()->willReturn($new_collected_data['mocked'])->shouldBeCalled();

		$revision_log = $this->createRevisionLog('svn://localhost/trunk');
		$revision_log->registerPlugin($plugin->reveal());
		$revision_log->refresh();
	}

	/**
	 * @dataProvider repositoryUrlDataProvider
	 */
	public function testRefreshWithCache($repository_url, $plugin_last_revision)
	{
		$collected_data = array(
			'mocked' => array('OLD_COLLECTED'),
		);

		$cache_invalidator = new RegExToken('/^main:[\d]+;plugin\(mocked\):[\d]+$/');

		if ( !isset($plugin_last_revision) ) {
			$this->repositoryConnector->getFirstRevision('svn://localhost')->willReturn(1000)->shouldBeCalled();
		}

		$this->repositoryConnector->getLastRevision('svn://localhost')->willReturn(1000)->shouldBeCalled();
		$this->repositoryConnector->getProjectUrl($repository_url)->willReturn('svn://localhost')->shouldBeCalled();

		$this->cacheManager
			->getCache('log:svn://localhost', $cache_invalidator)
			->willReturn($collected_data)
			->shouldBeCalled();

		$plugin = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\IRevisionLogPlugin');
		$plugin->getName()->willReturn('mocked')->shouldBeCalled();
		$plugin->getCacheInvalidator()->willReturn(5)->shouldBeCalled();
		$plugin->setCollectedData($collected_data['mocked'])->shouldBeCalled();

		$plugin->getLastRevision()->willReturn($plugin_last_revision)->shouldBeCalled();

		$revision_log = $this->createRevisionLog($repository_url);
		$revision_log->registerPlugin($plugin->reveal());
		$revision_log->refresh();
	}

	public function repositoryUrlDataProvider()
	{
		return array(
			array('svn://localhost', null),
			array('svn://localhost/trunk', 1000),
			array('svn://localhost/branches', 1000),
			array('svn://localhost/branches/branch-name', 1000),
			array('svn://localhost/tags', 1000),
			array('svn://localhost/tags/tag-name', 1000),
		);
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

		$this->repositoryConnector->getCommand($command_name, $param_string)->willReturn($command)->shouldBeCalled();
	}

	/**
	 * Creates revision log.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return RevisionLog
	 */
	protected function createRevisionLog($repository_url)
	{
		$revision_log = new RevisionLog(
			$repository_url,
			$this->repositoryConnector->reveal(),
			$this->cacheManager->reveal(),
			$this->io->reveal()
		);

		return $revision_log;
	}

}
