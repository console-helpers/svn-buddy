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


use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\ConsoleHelpers\ConsoleKit\WorkingDirectoryAwareTestCase;

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

		$this->_configEditor = $this->prophesize('ConsoleHelpers\\ConsoleKit\\Config\\ConfigEditor');
		$this->_processFactory = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Process\\IProcessFactory');
		$this->_io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
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

		$this->_expectCommand('svn --non-interactive --username user --version', 'OK');
		$this->assertEquals('OK', $repository_connector->getCommand('--version')->run());
	}

	public function testConfigPasswordUsed()
	{
		$repository_connector = $this->_createRepositoryConnector('', 'pass');

		$this->_expectCommand('svn --non-interactive --password pass --version', 'OK');
		$this->assertEquals('OK', $repository_connector->getCommand('--version')->run());
	}

	public function testSimpleCommand()
	{
		$this->_expectCommand('svn --non-interactive --version', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('--version')->run());
	}

	public function testCommandWithParams()
	{
		$this->_expectCommand('svn --non-interactive log -r 12', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '-r 12')->run());
	}

	public function testCommandWithPath()
	{
		$this->_expectCommand("svn --non-interactive log 'path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '{path/to/folder}')->run());
	}

	public function testCommandWithPathAndLeadingSlash()
	{
		$this->_expectCommand("svn --non-interactive log '/path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '{/path/to/folder}')->run());
	}

	public function testCommandWithPathAndParams()
	{
		$this->_expectCommand("svn --non-interactive log -r 12 'path/to/folder'", 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', '-r 12 {path/to/folder}')->run());
	}

	public function testCommandThatFails()
	{
		$thrown_exception = null;
		$this->_expectCommand('svn --non-interactive any', '', false);

		try {
			$this->_repositoryConnector->getCommand('any')->run();
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
svn --non-interactive any
Error #0:
error output
MSG;

		$this->assertEquals($error_msg, $thrown_exception->getMessage());
	}

	public function testGetPropertyFound()
	{
		$this->_expectCommand("svn --non-interactive propget test-p 'the/path'", 'OK');

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
			'ConsoleHelpers\\SVNBuddy\\Exception\\RepositoryCommandException',
			$exception_msg,
			0
		);

		$this->_expectCommand("svn --non-interactive propget test-p 'the/path'", '', false);

		$this->_repositoryConnector->getProperty('test-p', 'the/path');
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string  $command       Command.
	 * @param string  $output        Output.
	 * @param boolean $is_successful Should command be successful.
	 *
	 * @return void
	 */
	private function _expectCommand($command, $output, $is_successful = true)
	{
		$this->_process->getCommandLine()->willReturn($command)->shouldBeCalled();

		$expectation = $this->_process->mustRun(null)->shouldBeCalled();

		if ( $is_successful ) {
			$this->_process->getOutput()->willReturn($output)->shouldBeCalled();
		}
		else {
			$process = $this->_process;
			$expectation->will(function () use ($process) {
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
