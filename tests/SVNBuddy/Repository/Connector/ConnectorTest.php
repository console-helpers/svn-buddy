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
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class ConnectorTest extends \PHPUnit_Framework_TestCase
{

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
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	protected function setUp()
	{
		parent::setUp();

		$this->_configEditor = $this->prophesize('ConsoleHelpers\\ConsoleKit\\Config\\ConfigEditor');
		$this->_io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
		$this->_processFactory = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Process\\IProcessFactory');
		$this->_cacheManager = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager');

		// To get nice exception back when unexpected command is executed.
		$this->_processFactory
			->createProcess(Argument::any(), 1200)
			->will(function (array $args) {
				throw new \LogicException('The createProcess("' . $args[0] . '", 1200) call wasn\'t expected.');
			});

		$this->_repositoryConnector = $this->_createRepositoryConnector('', '');
	}

	/**
	 * @dataProvider baseCommandBuildingDataProvider
	 */
	public function testBaseCommandBuilding($username, $password, $expected_command)
	{
		$repository_connector = $this->_createRepositoryConnector($username, $password);

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
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('', '--version')->run());
	}

	public function testCommandWithoutParams()
	{
		$this->_expectCommand('svn --non-interactive log', 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log')->run());
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "log -r 5" sub-command contains spaces.
	 */
	public function testSubCommandWithSpace()
	{
		$this->_repositoryConnector->getCommand('log -r 5')->run();
	}

	/**
	 * @dataProvider commandWithParamsDataProvider
	 */
	public function testCommandWithParams($params, $expected_command)
	{
		$this->_expectCommand($expected_command, 'OK');
		$this->assertEquals('OK', $this->_repositoryConnector->getCommand('log', $params)->run());
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

	public function testGetCommandWithCaching()
	{
		$this->_expectCommand('svn --non-interactive info', 'OK');

		$this->_cacheManager->getCache('command:svn --non-interactive info', null)->willReturn(null)->shouldBeCalled();
		$this->_cacheManager->setCache('command:svn --non-interactive info', 'OK', null, 100)->shouldBeCalled();

		$this->_repositoryConnector->withCache(100)->getCommand('info')->run();
	}

	public function testGetProperty()
	{
		$this->_expectCommand("svn --non-interactive propget prop-name 'the/path'", 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('prop-name', 'the/path')
		);
	}

	public function testGetPropertyWithRevision()
	{
		$this->_expectCommand("svn --non-interactive propget prop-name 'the/path' --revision 5", 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('prop-name', 'the/path', 5)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "/path/to/folder" is not an URL.
	 */
	public function testGetPathFromNonUrl()
	{
		$this->_repositoryConnector->getPathFromUrl('/path/to/folder');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The URL "svn://" is malformed.
	 */
	public function testGetPathFromMalformedUrl()
	{
		$this->_repositoryConnector->getPathFromUrl('svn://');
	}

	public function testGetPathFromUrl()
	{
		$actual = $this->_repositoryConnector->getPathFromUrl('svn://repository.com/path/to/project');

		$this->assertEquals('/path/to/project', $actual);
	}

	public function testGetWorkingCopyUrlFromUrl()
	{
		$expected = 'svn://repository.com/path/to/project';

		$this->assertEquals($expected, $this->_repositoryConnector->getWorkingCopyUrl($expected));
	}

	/**
	 * @dataProvider svnInfoDataProvider
	 */
	public function testGetWorkingCopyUrlFromPath($svn_info)
	{
		$this->_expectCommand("svn --non-interactive info --xml '/path/to/working-copy'", $svn_info);

		$actual = $this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
		$this->assertEquals('svn://repository.com/path/to/project', $actual);
	}

	public function svnInfoDataProvider()
	{
		return array(
			'svn1.6' => array($this->getFixture('svn_info_16.xml')),
			'svn1.7' => array($this->getFixture('svn_info_17.xml')),
		);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The directory "/path/to/working-copy" not found in "svn info" command results.
	 */
	public function testGetWorkingCopyUrlWithBrokenSvnInfo()
	{
		$this->_expectCommand(
			"svn --non-interactive info --xml '/path/to/working-copy'",
			$this->getFixture('svn_info_broken.xml')
		);

		$this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
	}

	public function testGetWorkingCopyUrlOnOldFormatWorkingCopy()
	{
		$that = $this;

		$this->_io->writeln(array('', '<error>error message</error>', ''))->shouldBeCalled();
		$this->_io
			->askConfirmation('Run "svn upgrade"', false)
			->will(function () use ($that) {
				$that->_expectCommand(
					"svn --non-interactive info --xml '/path/to/working-copy'",
					$that->getFixture('svn_info_16.xml')
				);

				return true;
			})
			->shouldBeCalled();

		$this->_expectCommand(
			"svn --non-interactive info --xml '/path/to/working-copy'",
			'',
			'error message',
			RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED
		);

		$this->_expectCommand("svn --non-interactive upgrade '/path/to/working-copy'", 'OK');

		$actual = $this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
		$this->assertEquals('svn://repository.com/path/to/project', $actual);
	}

	public function testGetWorkingCopyUrlWithUnknownError()
	{
		$this->_expectCommand(
			"svn --non-interactive info --xml '/path/to/working-copy'",
			'',
			'error message',
			555
		);

		$this->setExpectedException(
			'ConsoleHelpers\\SVNBuddy\\Exception\\RepositoryCommandException',
			<<<MESSAGE
Command:
svn --non-interactive info --xml '/path/to/working-copy'
Error #555:
error message
MESSAGE
		);

		$this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
	}

	public function testGetWorkingCopyUrlOnOldFormatWorkingCopyAndUpgradeRejected()
	{
		$this->_io->writeln(array('', '<error>error message</error>', ''))->shouldBeCalled();
		$this->_io
			->askConfirmation('Run "svn upgrade"', false)
			->willReturn(false)
			->shouldBeCalled();

		$this->_expectCommand(
			"svn --non-interactive info --xml '/path/to/working-copy'",
			'',
			'error message',
			RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED
		);

		$exception_msg = <<<MESSAGE
Command:
svn --non-interactive info --xml '/path/to/working-copy'
Error #%d:
error message
MESSAGE;

		$this->setExpectedException(
			'ConsoleHelpers\\SVNBuddy\\Exception\\RepositoryCommandException',
			sprintf($exception_msg, RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED)
		);

		$this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string       $command    Command.
	 * @param string       $output     Output.
	 * @param string|null  $error_msg  Error msg.
	 * @param integer $error_code Error code.
	 */
	private function _expectCommand($command, $output, $error_msg = null, $error_code = 0)
	{
		$process = $this->prophesize('Symfony\\Component\\Process\\Process');
		$process->getCommandLine()->willReturn($command)->shouldBeCalled();

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
	 * Creates repository connector.
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 *
	 * @return Connector
	 */
	private function _createRepositoryConnector($username, $password)
	{
		$this->_configEditor->get('repository-connector.username')->willReturn($username)->shouldBeCalled();
		$this->_configEditor->get('repository-connector.password')->willReturn($password)->shouldBeCalled();

		return new Connector(
			$this->_configEditor->reveal(),
			$this->_processFactory->reveal(),
			$this->_io->reveal(),
			$this->_cacheManager->reveal()
		);
	}

	/**
	 * Returns fixture by name.
	 *
	 * @param string $name Fixture name.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When fixture wasn't found.
	 */
	protected function getFixture($name)
	{
		$fixture_filename = __DIR__ . '/fixtures/' . $name;

		if ( !file_exists($fixture_filename) ) {
			throw new \InvalidArgumentException('The "' . $name . '" fixture does not exist.');
		}

		return file_get_contents($fixture_filename);
	}

}
