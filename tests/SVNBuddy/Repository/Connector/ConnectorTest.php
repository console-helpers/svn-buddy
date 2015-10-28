<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Repository\Connector;


use aik099\SVNBuddy\Cache\CacheManager;
use aik099\SVNBuddy\ConsoleIO;
use aik099\SVNBuddy\Repository\Connector\Connector;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\aik099\SVNBuddy\WorkingDirectoryAwareTestCase;

class ConnectorTest extends WorkingDirectoryAwareTestCase
{

	/**
	 * Config editor.
	 *
	 * @var ObjectProphecy
	 */
	private $_configEditor;

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
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	protected function setUp()
	{
		parent::setUp();

		$this->_configEditor = $this->prophesize('aik099\\SVNBuddy\\Config\\ConfigEditor');
		$this->_processFactory = $this->prophesize('aik099\\SVNBuddy\\Process\\IProcessFactory');
		$this->_io = $this->prophesize('aik099\\SVNBuddy\\ConsoleIO');
		$this->_cacheManager = new CacheManager($this->getWorkingDirectory());
		$this->_process = $this->prophesize('Symfony\\Component\\Process\\Process');

		// Called from "__destruct".
		$this->_process->stop();

		$no_auto_connector = array(
			'testConfigUsernameUsed',
			'testConfigPasswordUsed',
			'testWorkingDirectoryCreation',
			'testBrokenLinuxEnvironment',
			'testBrokenWindowsEnvironment',
		);

		$with_exceptions = array(
			'testCommandThatFails',
			'testGetPropertyNotFound',
		);

		if ( !in_array($this->getName(false), $no_auto_connector) ) {
			$this->_repositoryConnector = $this->_createRepositoryConnector(
				'',
				'',
				in_array($this->getName(false), $with_exceptions) ? null : false
			);
		}
	}

	public function testConfigUsernameUsed()
	{
		$repository_connector = $this->_createRepositoryConnector('user', '');

		$this->_expectCommand('svn --username user --version', 'OK');
		$this->assertEquals('OK', $repository_connector->getCommand('--version')->run());
	}

	public function testConfigPasswordUsed()
	{
		$repository_connector = $this->_createRepositoryConnector('', 'pass');

		$this->_expectCommand('svn --password pass --version', 'OK');
		$this->assertEquals('OK', $repository_connector->getCommand('--version')->run());
	}

	public function testSimpleCommand()
	{
		$this->_expectCommand('svn --version', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('--version')->run());
	}

	public function testCommandWithParams()
	{
		$this->_expectCommand('svn log -r 12', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '-r 12')->run());
	}

	public function testCommandWithPath()
	{
		$this->_expectCommand("svn log 'path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '{path/to/folder}')->run());
	}

	public function testCommandWithPathAndLeadingSlash()
	{
		$this->_expectCommand("svn log '/path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '{/path/to/folder}')->run());
	}

	public function testCommandWithPathAndParams()
	{
		$this->_expectCommand("svn log -r 12 'path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '-r 12 {path/to/folder}')->run());
	}

	public function testCommandThatFails()
	{
		$thrown_exception = null;
		$this->_expectCommand('svn any', '', false);

		try {
			$this->_repositoryConnector->getCommand('any')->run();
		}
		catch ( \Exception $thrown_exception ) {
			$this->assertEquals(
				'aik099\\SVNBuddy\\Exception\\RepositoryCommandException',
				get_class($thrown_exception),
				'Exception of correct class was thrown'
			);
		}

		$this->assertNotNull($thrown_exception, 'Exception was thrown when command execution failed');

		$error_msg = <<<MSG
Command:
svn --non-interactive any
Error #0:
error output
MSG;

		$this->assertEquals($error_msg, $thrown_exception->getMessage());
	}

	public function testGetPropertyFound()
	{
		$this->_expectCommand("svn propget test-p 'the/path'", 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('test-p', 'the/path')
		);
	}

	public function testGetPropertyNotFound()
	{
		$exception_msg = <<<MSG
Command:
svn --non-interactive propget test-p 'the/path'
Error #0:
error output
MSG;

		$this->setExpectedException(
			'aik099\\SVNBuddy\\Exception\\RepositoryCommandException',
			$exception_msg,
			0
		);

		$this->_expectCommand("svn propget test-p 'the/path'", '', false);

		$this->_repositoryConnector->getProperty('test-p', 'the/path');
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string  $command        Command.
	 * @param string  $output         Output.
	 * @param boolean $is_successful  Should command be successful.
	 * @param boolean $is_interactive Is interactive.
	 *
	 * @return void
	 */
	private function _expectCommand($command, $output, $is_successful = true, $is_interactive = false)
	{
		if ( !$is_interactive ) {
			$this->_process->setInput('')->shouldBeCalled();
		}

		$patched_command = preg_replace('/^svn /', 'svn --non-interactive ', $command);
		$this->_process->getCommandLine()->willReturn($command)->shouldBeCalled();
		$this->_process->setCommandLine($patched_command)->will(function ($args, $process) {
			$process->getCommandLine()->willReturn($args[0]);
		})->shouldBeCalled();

		$expectation = $this->_process->mustRun(null)->shouldBeCalled();

		if ( $is_successful ) {
			$this->_process->getOutput()->willReturn($output)->shouldBeCalled();

			/*$this->_io->write(
				hm::matchesPattern('#svn command \([\d.]+ ms\): ' . preg_quote($command) . '#')
			)
			->shouldBeCalled();*/
		}
		else {
			$mock_definition = array(
				'isSuccessful' => false,
				'getExitCode' => 1,
				'getExitCodeText' => 'exit code text',
				'isOutputDisabled' => false,
				'getOutput' => 'normal output',
				'getErrorOutput' => 'error output',
			);

			foreach ( $mock_definition as $method_name => $return_value ) {
				$this->_process->{$method_name}()->willReturn($return_value)->shouldBeCalled();
			}

			$process = $this->_process;
			$expectation->will(function () use ($process) {
				throw new ProcessFailedException($process->reveal());
			});
		}

		$this->_processFactory->createProcess($command, 1200)->willReturn($this->_process)->shouldBeCalled();
	}

	/**
	 * Creates repository connector.
	 *
	 * @param string  $svn_username Username.
	 * @param string  $svn_password Password.
	 * @param boolean $is_verbose   Is verbose.
	 *
	 * @return Connector
	 */
	private function _createRepositoryConnector($svn_username, $svn_password, $is_verbose = false)
	{
		$this->_configEditor->get('repository-connector.username')->willReturn($svn_username)->shouldBeCalled();
		$this->_configEditor->get('repository-connector.password')->willReturn($svn_password)->shouldBeCalled();

		if ( isset($is_verbose) ) {
			$this->_io->isVerbose()->willReturn($is_verbose)->shouldBeCalled();
		}

		return new Connector(
			$this->_configEditor->reveal(),
			$this->_processFactory->reveal(),
			$this->_io->reveal(),
			$this->_cacheManager
		);
	}

}
