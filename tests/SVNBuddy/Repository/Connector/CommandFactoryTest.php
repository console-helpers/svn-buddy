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


use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Repository\Connector\CommandFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use Symfony\Component\Process\Process;

class CommandFactoryTest extends TestCase
{

	use ExpectException;

	/**
	 * Config editor.
	 *
	 * @var ObjectProphecy
	 */
	private $_configEditor;

	/**
	 * Console IO.
	 *
	 * @var ObjectProphecy
	 */
	private $_io;

	/**
	 * Process factory.
	 *
	 * @var ObjectProphecy
	 */
	private $_processFactory;

	/**
	 * Cache manager.
	 *
	 * @var ObjectProphecy
	 */
	private $_cacheManager;

	/**
	 * Command factory.
	 *
	 * @var CommandFactory
	 */
	private $_commandFactory;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->_configEditor = $this->prophesize(ConfigEditor::class);
		$this->_io = $this->prophesize(ConsoleIO::class);
		$this->_processFactory = $this->prophesize(IProcessFactory::class);
		$this->_cacheManager = $this->prophesize(CacheManager::class);

		// To get nice exception back when unexpected command is executed.
		$this->_processFactory
			->createProcess(Argument::any(), 1200)
			->will(function (array $args) {
				throw new \LogicException('The createProcess("' . $args[0] . '", 1200) call wasn\'t expected.');
			});

		$this->_commandFactory = $this->_createCommandFactory('', '');
	}

	/**
	 * @dataProvider baseCommandBuildingDataProvider
	 */
	public function testBaseCommandBuilding($username, $password, $expected_command)
	{
		$repository_connector = $this->_createCommandFactory($username, $password);

		$this->_expectCommand($expected_command, 'OK');
		$this->assertEquals('OK', $repository_connector->getCommand('', '--version')->run());
	}

	public function baseCommandBuildingDataProvider()
	{
		return array(
			'no username, no password' => array('', '', 'svn --non-interactive --version'),
			'username, no password' => array('user', '', 'svn --non-interactive --username user --version'),
			'no username, password' => array('', 'pass', 'svn --non-interactive --password pass --version'),
			'username, password' => array(
				'user',
				'pass',
				'svn --non-interactive --username user --password pass --version',
			),
		);
	}

	public function testCommandWithoutSubCommand()
	{
		$this->_expectCommand('svn --non-interactive --version', 'OK');
		$this->assertEquals('OK', $this->_commandFactory->getCommand('', '--version')->run());
	}

	public function testCommandWithoutParams()
	{
		$this->_expectCommand('svn --non-interactive log', 'OK');
		$this->assertEquals('OK', $this->_commandFactory->getCommand('log')->run());
	}

	public function testSubCommandWithSpace()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "log -r 5" sub-command contains spaces.');

		$this->_commandFactory->getCommand('log -r 5')->run();
	}

	/**
	 * @dataProvider commandWithParamsDataProvider
	 */
	public function testCommandWithParams($params, $expected_command)
	{
		$this->_expectCommand($expected_command, 'OK');
		$this->assertEquals('OK', $this->_commandFactory->getCommand('log', $params)->run());
	}

	public function commandWithParamsDataProvider()
	{
		return array(
			'regular param' => array('-r 12', 'svn --non-interactive log -r 12'),
			'path param' => array('{path/to/folder}', "svn --non-interactive log 'path/to/folder'"),
			'regular and path param' => array(
				'-r 12 {path/to/folder}',
				"svn --non-interactive log -r 12 'path/to/folder'",
			),
		);
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string      $command    Command.
	 * @param string      $output     Output.
	 * @param string|null $error_msg  Error msg.
	 * @param integer     $error_code Error code.
	 */
	private function _expectCommand($command, $output, $error_msg = null, $error_code = 0)
	{
		$process = $this->prophesize(Process::class);

		$expectation = $process
			->mustRun(strpos($command, 'upgrade') !== false ? Argument::type('callable') : null)
			->shouldBeCalled();

		if ( isset($error_code) && isset($error_msg) ) {
			$expectation->willThrow(
				new RepositoryCommandException($command, 'svn: E' . $error_code . ': ' . $error_msg)
			);
		}
		else {
			$expectation->willReturn(null);
			$process->getOutput()->willReturn($output)->shouldBeCalled();
		}

		$this->_io->isVerbose()->willReturn(false);
		$this->_io->isDebug()->willReturn(false);

		$this->_processFactory->createProcess($command, 1200)->willReturn($process)->shouldBeCalled();
	}

	/**
	 * Creates command factory.
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 *
	 * @return CommandFactory
	 */
	private function _createCommandFactory($username, $password)
	{
		$this->_configEditor->get('repository-connector.username')->willReturn($username)->shouldBeCalled();
		$this->_configEditor->get('repository-connector.password')->willReturn($password)->shouldBeCalled();

		return new CommandFactory(
			$this->_configEditor->reveal(),
			$this->_processFactory->reveal(),
			$this->_io->reveal(),
			$this->_cacheManager->reveal()
		);
	}

}
