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
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\RegExToken;

class CommandTest extends \PHPUnit_Framework_TestCase
{

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

	protected function setUp()
	{
		parent::setUp();

		$this->_process = $this->prophesize('Symfony\\Component\\Process\\Process');
		$this->_io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
		$this->_cacheManager = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager');

		$this->_command = $this->_createCommand();
	}

	/**
	 * @dataProvider runWithoutCachingDataProvider
	 */
	public function testRunWithoutCaching($callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = 'svn log --xml';
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = 'svn log';
			$process_output = 'OK';
		}

		$this->_process->getCommandLine()->willReturn($command_line)->shouldBeCalled();

		$this->_process->mustRun($callback)->shouldBeCalled();
		$this->_process->getOutput()->willReturn($process_output)->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$this->assertCommandOutput($callback, $is_xml, $process_output);
	}

	public function runWithoutCachingDataProvider()
	{
		$callback = function ($output, $type) {};

		return array(
			'w/o callback, not xml' => array(null, false),
			'w/o callback, is xml' => array(null, true),
			'w callback, not xml' => array($callback, false),
			'w callback, is xml' => array($callback, true),
		);
	}

	/**
	 * @dataProvider runWithCacheDataProvider
	 */
	public function testRunWithMissingCache($duration, $invalidator, $callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = 'svn log --xml';
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = 'svn log';
			$process_output = 'OK';
		}

		$this->_process->getCommandLine()->willReturn($command_line)->shouldBeCalled();
		$this->_process->mustRun($callback)->shouldBeCalled();
		$this->_process->getOutput()->willReturn($process_output)->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();

		$this->_cacheManager
			->getCache('command:' . $command_line, $invalidator)
			->willReturn(null)
			->shouldBeCalled();
		$this->_cacheManager
			->setCache('command:' . $command_line, $process_output, $invalidator, $duration)
			->shouldBeCalled();

		if ( isset($duration) ) {
			$this->_command->setCacheDuration($duration);
		}

		if ( isset($invalidator) ) {
			$this->_command->setCacheInvalidator($invalidator);
		}

		$this->assertCommandOutput($callback, $is_xml, $process_output);
	}

	/**
	 * @dataProvider runWithCacheDataProvider
	 */
	public function testRunWithExistingCache($duration, $invalidator, $callback, $is_xml)
	{
		if ( $is_xml ) {
			$command_line = 'svn log --xml';
			$process_output = '<log><logentry/></log>';
		}
		else {
			$command_line = 'svn log';
			$process_output = 'OK';
		}

		$this->_process->getCommandLine()->willReturn($command_line)->shouldBeCalled();
		$this->_process->mustRun($callback)->shouldNotBeCalled();

		$this->_cacheManager
			->getCache('command:' . $command_line, $invalidator)
			->willReturn($process_output)
			->shouldBeCalled();
		$this->_cacheManager->setCache(Argument::any())->shouldNotBeCalled();

		if ( isset($duration) ) {
			$this->_command->setCacheDuration($duration);
		}

		if ( isset($invalidator) ) {
			$this->_command->setCacheInvalidator($invalidator);
		}

		$this->assertCommandOutput($callback, $is_xml, $process_output);
	}

	public function runWithCacheDataProvider()
	{
		$callback = function ($output, $type) {};

		return array(
			'duration only, w/o callback, not xml' => array(100, null, null, false),
			'invalidator only, w/o callback, not xml' => array(null, 'invalidator', null, false),
			'duration and invalidator, w/o callback, not xml' => array(100, 'invalidator', null, false),

			'duration only, w/o callback, is xml' => array(100, null, null, true),
			'invalidator only, w/o callback, is xml' => array(null, 'invalidator', null, true),
			'duration and invalidator, w/o callback, is xml' => array(100, 'invalidator', null, true),

			'duration only, w callback, not xml' => array(100, null, $callback, false),
			'invalidator only, w callback, not xml' => array(null, 'invalidator', $callback, false),
			'duration and invalidator, w callback, not xml' => array(100, 'invalidator', $callback, false),

			'duration only, w callback, is xml' => array(100, null, $callback, true),
			'invalidator only, w callback, is xml' => array(null, 'invalidator', $callback, true),
			'duration and invalidator, w callback, is xml' => array(100, 'invalidator', $callback, true),
		);
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
		$this->_process->getCommandLine()->willReturn('svn log')->shouldBeCalled();
		$this->_process->mustRun(null)->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(true)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$this->_io
			->writeln(new RegExToken("#^\n<fg=white;bg=magenta>\[svn, [\d\.]+s\]: svn log</>$#s"))
			->shouldBeCalled();

		$this->_command->run();
	}

	public function testDebugOutput()
	{
		$this->_process->getCommandLine()->willReturn('svn log')->shouldBeCalled();
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
		$this->_process->getCommandLine()->willReturn('svn log')->shouldBeCalled();
		$this->_process
			->mustRun(Argument::type('callable'))
			->will(function (array $args) use ($output_type) {
				return call_user_func($args[0], $output_type, 'TEXT');
			})
			->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false)->shouldBeCalled();
		$this->_io->isDebug()->willReturn(false)->shouldBeCalled();
		$this->_io->write($expected_output)->shouldBeCalled();
		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$actual_result = $this->_command->runLive(array('TE' => 'KE'));
		$this->assertEquals('OK', $actual_result);
	}

	public function runLiveDataProvider()
	{
		return array(
			array(Process::OUT, 'KEXT'),
			array(Process::ERR, '<error>ERR:</error> KEXT'),
		);
	}

	public function testRunError()
	{
		$this->_process->getCommandLine()->willReturn('svn log')->shouldBeCalled();

		$process = $this->_process;
		$this->_process
			->mustRun(null)
			->will(function () use ($process) {
				$mock_definition = array(
					'isSuccessful' => false,
					'getExitCode' => 1,
					'getExitCodeText' => 'exit code text',
					'isOutputDisabled' => false,
					'getOutput' => 'normal output',
					'getErrorOutput' => 'error output',
				);

				foreach ( $mock_definition as $method_name => $return_value ) {
					$process->{$method_name}()->willReturn($return_value)->shouldBeCalled();
				}

				throw new ProcessFailedException($process->reveal());
			})
			->shouldBeCalled();

		$this->_process->getOutput()->shouldNotBeCalled();
		$this->_io->isVerbose()->shouldNotBeCalled();
		$this->_io->isDebug()->shouldNotBeCalled();

		$this->_cacheManager->getCache(Argument::any())->shouldNotBeCalled();

		$thrown_exception = null;

		try {
			$this->_command->run();
		}
		catch ( \Exception $thrown_exception ) {
			$this->assertEquals(
				'ConsoleHelpers\\SVNBuddy\\Exception\\RepositoryCommandException',
				get_class($thrown_exception),
				'Exception of correct class was thrown'
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
	 * Creates command.
	 *
	 * @return Command
	 */
	private function _createCommand()
	{
		return new Command($this->_process->reveal(), $this->_io->reveal(), $this->_cacheManager->reveal());
	}

}
