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


use aik099\SVNBuddy\Exception\RepositoryCommandException;
use aik099\SVNBuddy\RepositoryConnector\RepositoryConnector;
use Mockery as m;
use Mockery\MockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RepositoryConnectorTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Config.
	 *
	 * @var MockInterface
	 */
	private $_config;

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
	 * Logger interface.
	 *
	 * @var OutputInterface
	 */
	private $_output;

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
		$this->_config = m::mock('aik099\\SVNBuddy\\Config');
		$this->_processFactory = m::mock('aik099\\SVNBuddy\\Process\\IProcessFactory');
		$this->_output = m::mock('Symfony\\Component\\Console\\Output\\OutputInterface');
		$this->_process = m::mock('Symfony\\Component\\Process\\Process');

		// Called from "__destruct".
		$this->_process->shouldReceive('stop');

		$no_auto_connector = array(
			'testConfigUsernameUsed',
			'testConfigPasswordUsed',
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

		$this->_expectCommand('svn --username user --non-interactive --version', 'OK');
		$this->assertEquals('OK', $repository_connector->command('--version'));
	}

	public function testConfigPasswordUsed()
	{
		$repository_connector = $this->_createRepositoryConnector('', 'pass');

		$this->_expectCommand('svn --password pass --non-interactive --version', 'OK');
		$this->assertEquals('OK', $repository_connector->command('--version'));
	}

	public function testSimpleCommand()
	{
		$this->_expectCommand('svn --non-interactive --version', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->command('--version'));
	}

	public function testCommandWithParams()
	{
		$this->_expectCommand('svn --non-interactive log -r 12', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->command('log', '-r 12'));
	}

	public function testCommandWithPath()
	{
		$this->_expectCommand("svn --non-interactive log 'path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->command('log', null, 'path/to/folder'));
	}

	public function testCommandWithPathAndLeadingSlash()
	{
		$this->_expectCommand("svn --non-interactive log '/path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->command('log', null, '/path/to/folder'));
	}

	public function testCommandWithPathAndParams()
	{
		$this->_expectCommand("svn --non-interactive log 'path/to/folder' -r 12", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->command('log', '-r 12', 'path/to/folder'));
	}

	public function testCommandThatFails()
	{
		$mock_definition = array(
			'isSuccessful' => false,
			'getCommandLine' => 'svn --non-interactive any',
			'getExitCode' => 127,
			'getExitCodeText' => 'Command not found',
			'isOutputDisabled' => false,
			'getOutput' => 'normal output',
			'getErrorOutput' => 'error output',
		);

		foreach ( $mock_definition as $method_name => $return_value ) {
			$this->_process->shouldReceive($method_name)->atLeast()->once()->andReturn($return_value);
		}

		$this->_process->shouldReceive('mustRun')
			->atLeast()
			->once()
			->andThrow(new ProcessFailedException($this->_process));

		$this->_processFactory->shouldReceive('createProcess')
			->with('svn --non-interactive any')
			->once()
			->andReturn($this->_process);

		/** @var RepositoryCommandException $thrown_exception */
		$thrown_exception = null;

		try {
			$this->_repositoryConnector->command('any');
		}
		catch ( \Exception $thrown_exception ) {
			$this->assertEquals(
				'aik099\\SVNBuddy\\Exception\\RepositoryCommandException',
				get_class($thrown_exception),
				'Exception of correct class was thrown'
			);
		}

		$this->assertNotNull($thrown_exception, 'Exception was thrown when command execution failed');

		$this->assertEquals('svn --non-interactive any', $thrown_exception->getCommand());
		$this->assertEquals(127, $thrown_exception->getExitCode());
		$this->assertEquals('normal output', $thrown_exception->getOutput());
		$this->assertEquals('error output', $thrown_exception->getErrorOutput());
		$this->assertEquals(
			'Execution of repository command "svn --non-interactive any" failed with output: error output',
			$thrown_exception->getMessage()
		);
	}

	public function testGetPropertyFound()
	{
		$this->_expectCommand("svn --non-interactive propget test-p 'the/path'", 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('test-p', 'the/path')
		);
	}

	/**
	 * @expectedException \aik099\SVNBuddy\Exception\RepositoryCommandException
	 * @expectedExceptionMessage Execution of repository command "svn --non-interactive propget test-p 'the/path'" failed with output: dummy error text
	 */
	public function testGetPropertyNotFound()
	{
		$this->_expectCommand("svn --non-interactive propget test-p 'the/path'", '', 5);

		$this->_repositoryConnector->getProperty('test-p', 'the/path');
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string  $command   Command.
	 * @param string  $output    Output.
	 * @param integer $exit_code Exit code.
	 *
	 * @return void
	 */
	private function _expectCommand($command, $output, $exit_code = 0)
	{
		$expectation = $this->_process->shouldReceive('mustRun')->once();

		if ( $exit_code === 0 ) {
			$this->_process->shouldReceive('getOutput')->once()->andReturn($output);

			/*$this->_output->shouldReceive('write')
				->with(hm::matchesPattern('#svn command \([\d.]+ ms\): ' . preg_quote($command) . '#'))
				->once();*/
		}
		else {
			$exception = new RepositoryCommandException($command, $exit_code, $output, 'dummy error text');
			$expectation->andThrow($exception);
		}

		$this->_processFactory->shouldReceive('createProcess')->with($command)->once()->andReturn($this->_process);
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
		$this->_config->shouldReceive('get')->with('svn-username')->once()->andReturn($svn_username);
		$this->_config->shouldReceive('get')->with('svn-password')->once()->andReturn($svn_password);

		if ( isset($is_verbose) ) {
			$this->_output->shouldReceive('getVerbosity')->once()->andReturn(
				$is_verbose ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL
			);
		}

		return new RepositoryConnector($this->_config, $this->_processFactory, $this->_output);
	}

}
