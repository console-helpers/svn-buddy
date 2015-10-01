<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\RepositoryConnector;


use aik099\SVNBuddy\Cache\CacheManager;
use aik099\SVNBuddy\ConsoleIO;
use aik099\SVNBuddy\Exception\RepositoryCommandException;
use aik099\SVNBuddy\RepositoryConnector\RepositoryConnector;
use Mockery as m;
use Mockery\MockInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\aik099\SVNBuddy\WorkingDirectoryTest;

class RepositoryConnectorTest extends WorkingDirectoryTest
{

	/**
	 * Config editor.
	 *
	 * @var MockInterface
	 */
	private $_configEditor;

	/**
	 * Process factory.
	 *
	 * @var MockInterface
	 */
	private $_processFactory;

	/**
	 * Process.
	 *
	 * @var MockInterface
	 */
	private $_process;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
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
	 * @var RepositoryConnector
	 */
	private $_repositoryConnector;

	/**
	 * Prepares fixture.
	 *
	 * @return void
	 */
	protected function setUp()
	{
		parent::setUp();

		$this->_configEditor = m::mock('aik099\\SVNBuddy\\Config\\ConfigEditor');
		$this->_processFactory = m::mock('aik099\\SVNBuddy\\Process\\IProcessFactory');
		$this->_io = m::mock('aik099\\SVNBuddy\\ConsoleIO');
		$this->_cacheManager = new CacheManager($this->getWorkingDirectory());
		$this->_process = m::mock('Symfony\\Component\\Process\\Process');

		// Called from "__destruct".
		$this->_process->shouldReceive('stop');

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
		$patched_command = preg_replace('/^svn /', 'svn --non-interactive ', $command);

		if ( !$is_interactive ) {
			$this->_process->shouldReceive('setInput')->with('')->once();
		}

		$this->_process->shouldReceive('getCommandLine')->andReturn($command, $patched_command);
		$this->_process->shouldReceive('setCommandLine')->andReturn($patched_command);

		$expectation = $this->_process->shouldReceive('mustRun')->once();

		if ( $is_successful ) {
			$this->_process->shouldReceive('getOutput')->once()->andReturn($output);

			/*$this->_io->shouldReceive('write')
				->with(hm::matchesPattern('#svn command \([\d.]+ ms\): ' . preg_quote($command) . '#'))
				->once();*/
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
				$this->_process->shouldReceive($method_name)->atLeast()->once()->andReturn($return_value);
			}

			$process = $this->_process;
			$expectation->andThrow('Exception')->andReturnUsing(function () use ($process) {
				return new ProcessFailedException($process);
			});
		}

		$this->_processFactory->shouldReceive('createProcess')
			->with($command, 1200)
			->once()
			->andReturn($this->_process);
	}

	/**
	 * Creates repository connector.
	 *
	 * @param string  $svn_username Username.
	 * @param string  $svn_password Password.
	 * @param boolean $is_verbose   Is verbose.
	 *
	 * @return RepositoryConnector
	 */
	private function _createRepositoryConnector($svn_username, $svn_password, $is_verbose = false)
	{
		$this->_configEditor->shouldReceive('get')
			->with('repository-connector.username')
			->once()
			->andReturn($svn_username);
		$this->_configEditor->shouldReceive('get')
			->with('repository-connector.password')
			->once()
			->andReturn($svn_password);

		if ( isset($is_verbose) ) {
			$this->_io->shouldReceive('isVerbose')->once()->andReturn($is_verbose);
		}

		return new RepositoryConnector($this->_configEditor, $this->_processFactory, $this->_io, $this->_cacheManager);
	}

}
