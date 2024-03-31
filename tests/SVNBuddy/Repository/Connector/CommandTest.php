<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\Connector;


use ConsoleHelpers\SVNBuddy\Repository\Connector\Command;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class CommandTest extends AbstractTestCase
{

	/**
	 * Process factory.
	 *
	 * @var ObjectProphecy
	 */
	private $_processFactory;

	/**
	 * Process.
	 *
	 * @var ObjectProphecy
	 */
	private $_process;

	/**
	 * Console IO.
	 *
	 * @var ObjectProphecy
	 */
	private $_io;

	/**
	 * Cache manager.
	 *
	 * @var ObjectProphecy
	 */
	private $_cacheManager;

	/**
	 * Command.
	 *
	 * @var Command
	 */
	private $_command;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->_processFactory = $this->prophesize(IProcessFactory::class);
		$this->_process = $this->prophesize(Process::class);
		$this->_io = $this->prophesize(ConsoleIO::class);
		$this->_cacheManager = $this->prophesize(CacheManager::class);
	}

	/**
	 * @dataProvider runWithoutCachingDataProvider
	 */
	public function testRunWithoutCaching($use_callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = array('svn', 'log', '--xml');
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = array('svn', 'log');
			$process_output = 'OK';
		}

		$this->_command = $this->_createCommand($command_line);

		$callback_output = null;
		$callback = $this->createRunCallback($use_callback, $callback_output);

		$this->_process
			->mustRun($callback)
			->will(function (array $args) use ($process_output) {
				if ( is_callable($args[0]) ) {
					call_user_func($args[0], Process::OUT, $process_output);
				}

				return $this;
			})
			->shouldBeCalled();

		$this->_process->getOutput()->willReturn($process_output)->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$this->assertCommandOutput($callback, $is_xml, $process_output);

		if ( $use_callback ) {
			$this->assertEquals($process_output, $callback_output);
		}
	}

	public static function runWithoutCachingDataProvider()
	{
		return array(
			'w/o callback, not xml' => array(false, false),
			'w/o callback, is xml' => array(false, true),
			'w callback, not xml' => array(true, false),
			'w callback, is xml' => array(true, true),
		);
	}

	/**
	 * @dataProvider runWithCacheDataProvider
	 */
	public function testRunWithMissingCache($duration, $invalidator, $overwrite, $use_callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = array('svn', 'log', '--xml');
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = array('svn', 'log');
			$process_output = 'OK';
		}

		$this->_command = $this->_createCommand($command_line);

		$callback_output = null;
		$callback = $this->createRunCallback($use_callback, $callback_output);

		$this->configureProcess($callback, $process_output, true);

		$command_string = implode(' ', $command_line);

		if ( $overwrite === true ) {
			$this->_cacheManager->deleteCache('misc/command:' . $command_string, $duration)->shouldBeCalled();
		}
		else {
			$this->_cacheManager
				->getCache('misc/command:' . $command_string, $invalidator, $duration)
				->willReturn(null)
				->shouldBeCalled();
		}

		$this->_cacheManager
			->setCache('misc/command:' . $command_string, $process_output, $invalidator, $duration)
			->shouldBeCalled();

		if ( isset($duration) ) {
			$this->_command->setCacheDuration($duration);
		}

		if ( isset($invalidator) ) {
			$this->_command->setCacheInvalidator($invalidator);
		}

		if ( isset($overwrite) ) {
			$this->_command->setCacheOverwrite($overwrite);
		}

		$this->assertCommandOutput($callback, $is_xml, $process_output);

		if ( $use_callback ) {
			$this->assertEquals($process_output, $callback_output);
		}
	}

	/**
	 * @dataProvider runWithCacheDataProvider
	 */
	public function testRunWithExistingCache($duration, $invalidator, $overwrite, $use_callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = array('svn', 'log', '--xml');
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = array('svn', 'log');
			$process_output = 'OK';
		}

		$this->_command = $this->_createCommand($command_line, $overwrite === true);

		$callback_output = null;
		$callback = $this->createRunCallback($use_callback, $callback_output);

		$this->configureProcess($callback, $process_output, $overwrite === true);

		$command_string = implode(' ', $command_line);

		if ( $overwrite === true ) {
			$this->_cacheManager->deleteCache('misc/command:' . $command_string, $duration)->shouldBeCalled();
			$this->_cacheManager
				->setCache('misc/command:' . $command_string, $process_output, $invalidator, $duration)
				->shouldBeCalled();
		}
		else {
			$this->_cacheManager
				->getCache('misc/command:' . $command_string, $invalidator, $duration)
				->willReturn($process_output)
				->shouldBeCalled();
			$this->_cacheManager->setCache(Argument::any())->shouldNotBeCalled();
		}

		if ( isset($duration) ) {
			$this->_command->setCacheDuration($duration);
		}

		if ( isset($invalidator) ) {
			$this->_command->setCacheInvalidator($invalidator);
		}

		if ( isset($overwrite) ) {
			$this->_command->setCacheOverwrite($overwrite);
		}

		$this->assertCommandOutput($callback, $is_xml, $process_output);

		if ( $use_callback ) {
			$this->assertEquals($process_output, $callback_output);
		}
	}

	/**
	 * Configures process.
	 *
	 * @param callable|null $callback       Callback.
	 * @param string        $process_output Process output.
	 * @param boolean       $will_run       Process will be executed.
	 *
	 * @return void
	 */
	protected function configureProcess($callback, $process_output, $will_run)
	{
		if ( !$will_run ) {
			$this->_process->mustRun($callback)->shouldNotBeCalled();

			return;
		}

		$this->_process
			->mustRun($callback)
			->will(function (array $args) use ($process_output) {
				if ( is_callable($args[0]) ) {
					call_user_func($args[0], Process::OUT, $process_output);
				}

				return $this;
			})
			->shouldBeCalled();

		$this->_process->getOutput()->willReturn($process_output)->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
	}

	public static function runWithCacheDataProvider()
	{
		return array(
			'duration only, w/o callback, not xml' => array(100, null, null, false, false),
			'invalidator only, w/o callback, not xml' => array(null, 'invalidator', null, false, false),
			'duration and invalidator, w/o callback, not xml' => array(100, 'invalidator', null, false, false),

			'duration only, w/o callback, is xml' => array(100, null, null, false, true),
			'invalidator only, w/o callback, is xml' => array(null, 'invalidator', null, false, true),
			'duration and invalidator, w/o callback, is xml' => array(100, 'invalidator', null, false, true),

			'duration only, w callback, not xml' => array(100, null, null, true, false),
			'invalidator only, w callback, not xml' => array(null, 'invalidator', null, true, false),
			'duration and invalidator, w callback, not xml' => array(100, 'invalidator', null, true, false),

			'duration only, w callback, is xml' => array(100, null, null, true, true),
			'invalidator only, w callback, is xml' => array(null, 'invalidator', null, true, true),
			'duration and invalidator, w callback, is xml' => array(100, 'invalidator', null, true, true),

			'duration only, cache overwrite (true), w/o callback, not xml' => array(100, null, true, false, false),
			'duration only, cache overwrite (false), w/o callback, not xml' => array(100, null, false, false, false),
			'duration only, cache overwrite (null), w/o callback, not xml' => array(100, null, null, false, false),
		);
	}

	/**
	 * Creates callback that checks, that it was invoked.
	 *
	 * @param boolean $use_callback    Determines if callback should be created.
	 * @param boolean $callback_output Records if callback was called.
	 *
	 * @return callable|null
	 */
	protected function createRunCallback($use_callback, &$callback_output)
	{
		if ( $use_callback ) {
			$callback_output = null;

			return function ($type, $buffer) use (&$callback_output) {
				$callback_output = $buffer;
			};
		}

		return null;
	}

	/**
	 * Asserts command output.
	 *
	 * @param callable $callback       Callback.
	 * @param boolean  $is_xml         Is xml expected.
	 * @param string   $process_output Process output.
	 *
	 * @return void
	 */
	protected function assertCommandOutput($callback, $is_xml, $process_output)
	{
		$actual_command_output = $this->_command->run($callback);

		if ( $is_xml ) {
			$this->assertInstanceOf('SimpleXMLElement', $actual_command_output);
			$expected_command_output = new \SimpleXMLElement($process_output);
			$this->assertEquals($expected_command_output->asXML(), $actual_command_output->asXML());
		}
		else {
			$this->assertEquals($process_output, $actual_command_output);
		}
	}

	public function testVerboseOutput()
	{
		$this->_command = $this->_createCommand(array('svn', 'log'));

		$this->_process->mustRun(null)->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(true)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$this->_io->writeln(Argument::that(function ($messages) {
			if ( count($messages) !== 2 || $messages[0] !== '' ) {
				return false;
			}

			return preg_replace('/\[svn, [\d.]+s\]/', '[svn, 0s]', $messages[1]) === '<debug>[svn, 0s]: svn log</debug>';
		}))->shouldBeCalled();

		$this->_command->run();
	}

	public function testDebugOutput()
	{
		$this->_command = $this->_createCommand(array('svn', 'log'));

		$this->_process->mustRun(null)->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(true)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$this->_io
			->writeln('OK', OutputInterface::OUTPUT_RAW)
			->shouldBeCalled();

		$this->_command->run();
	}

	/**
	 * @dataProvider runLiveDataProvider
	 */
	public function testRunLive($output_type, $expected_output)
	{
		$this->_command = $this->_createCommand(array('svn', 'log'));

		$this->_process
			->mustRun(Argument::type('callable'))
			->will(function (array $args) use ($output_type) {
				call_user_func($args[0], $output_type, "TEX\nT");

				return $this;
			})
			->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_io->write($expected_output)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$actual_result = $this->_command->runLive(array('TE' => 'KE', '/([TX])/' => '{$1}'));
		$this->assertEquals('OK', $actual_result);
	}

	public static function runLiveDataProvider()
	{
		return array(
			array(Process::OUT, "KE{X}\n{T}"),
			array(Process::ERR, "<error>ERR:</error> KE{X}\n{T}"),
		);
	}

	public function testRunError()
	{
		$this->_command = $this->_createCommand(array('svn', 'log'));

		$exception = $this->prophesize(ProcessFailedException::class);

		$this->_process->mustRun(null)->willThrow($exception->reveal())->shouldBeCalled();
		$this->_process->getErrorOutput()->willReturn('error output')->shouldBeCalled();

		$this->_process->getOutput()->shouldNotBeCalled();
		$this->_io->isVerbose()->shouldNotBeCalled();
		$this->_io->isDebug()->shouldNotBeCalled();

		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$thrown_exception = null;

		try {
			$this->_command->run();
		}
		catch ( \Exception $thrown_exception ) {
			$this->assertInstanceOf(
				RepositoryCommandException::class,
				$thrown_exception,
				sprintf(
					'Exception of correct class was thrown' . PHP_EOL . 'Message:' . PHP_EOL . '%s',
					$thrown_exception->getMessage()
				)
			);
		}

		$this->assertNotNull($thrown_exception, 'Exception was thrown when command execution failed');

		$error_msg = <<<MSG
Command:
svn log
Error #0:
error output
MSG;

		$this->assertEquals($error_msg, $thrown_exception->getMessage());
	}

	/**
	 * @dataProvider cacheKeyFormingDataProvider
	 */
	public function testCacheKeyForming($repository_url, $cache_namespace)
	{
		$command_line = array('svn', 'log', $repository_url, $repository_url);
		$process_output = 'OK';

		$this->_command = $this->_createCommand($command_line, false);

		$this->_process->mustRun(null)->shouldNotBeCalled();

		$this->_cacheManager
			->getCache($cache_namespace . ':' . implode(' ', $command_line), null, '1 minute')
			->willReturn($process_output)
			->shouldBeCalled();
		$this->_cacheManager->setCache(Argument::any())->shouldNotBeCalled();

		$this->_command->setCacheDuration('1 minute');

		$this->assertEquals($process_output, $this->_command->run());
	}

	public static function cacheKeyFormingDataProvider()
	{
		return array(
			'no path' => array('svn://domain.tld', 'domain.tld/command'),
			'root path (trailing)' => array('svn://domain.tld/', 'domain.tld/command'),
			'path (no trailing)' => array('svn://domain.tld/path', 'domain.tld/command'),
			'path (trailing)' => array('svn://domain.tld/path/', 'domain.tld/command'),

			'no path; user' => array('svn://user@domain.tld', 'user@domain.tld/command'),
			'root path (trailing); user' => array('svn://user@domain.tld/', 'user@domain.tld/command'),
			'path (no trailing); user' => array('svn://user@domain.tld/path', 'user@domain.tld/command'),
			'path (trailing); user' => array('svn://user@domain.tld/path/', 'user@domain.tld/command'),

			'no path; port' => array('svn://domain.tld:1234', 'domain.tld:1234/command'),
			'root path (trailing); port' => array('svn://domain.tld:1234/', 'domain.tld:1234/command'),
			'path (no trailing); port' => array('svn://domain.tld:1234/path', 'domain.tld:1234/command'),
			'path (trailing); port' => array('svn://domain.tld:1234/path/', 'domain.tld:1234/command'),

			'no path; user; port' => array('svn://user@domain.tld:1234', 'user@domain.tld:1234/command'),
			'root path (trailing); user; port' => array('svn://user@domain.tld:1234/', 'user@domain.tld:1234/command'),
			'path (no trailing); user; port' => array('svn://user@domain.tld:1234/path', 'user@domain.tld:1234/command'),
			'path (trailing); user; port' => array('svn://user@domain.tld:1234/path/', 'user@domain.tld:1234/command'),

			'url with revision' => array('svn://in-portal.org/in-bulletin/branches/5.1.x/@12653', 'in-portal.org/command'),
		);
	}

	/**
	 * Creates command.
	 *
	 * @param array   $command_line Command line.
	 * @param boolean $use_process  Use process.
	 *
	 * @return Command
	 */
	private function _createCommand(array $command_line, $use_process = true)
	{
		if ( $use_process ) {
			$this->_processFactory->createProcess($command_line, 1200)->willReturn($this->_process)->shouldBeCalled();
		}

		return new Command(
			$command_line,
			$this->_io->reveal(),
			$this->_cacheManager->reveal(),
			$this->_processFactory->reveal()
		);
	}

}
