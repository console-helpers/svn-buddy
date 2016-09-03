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

	const DUMMY_REPO = 'svn://repository.com/path/to/project';

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

		$this->_cacheManager->getCache('misc/command:svn --non-interactive info', null, 100)->willReturn(null)->shouldBeCalled();
		$this->_cacheManager->setCache('misc/command:svn --non-interactive info', 'OK', null, 100)->shouldBeCalled();

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
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetWorkingCopyUrlFromUrl($given_repository_url, $used_repository_url)
	{
		$this->assertEquals(
			$used_repository_url,
			$this->_repositoryConnector->getWorkingCopyUrl($given_repository_url)
		);
	}

	/**
	 * @dataProvider svnInfoDataProvider
	 */
	public function testGetWorkingCopyUrlFromPath($raw_command_output)
	{
		$path = '/path/to/working-copy';
		$raw_command = "svn --non-interactive info --xml '" . $path . "'";

		$this->_cacheManager->getCache('misc/command:' . $raw_command, null, '1 year')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('misc/command:' . $raw_command, $raw_command_output, null, '1 year')
			->shouldBeCalled();

		$this->_expectCommand($raw_command, $raw_command_output);

		$actual = $this->_repositoryConnector->getWorkingCopyUrl($path);
		$this->assertEquals(self::DUMMY_REPO, $actual);
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
				// Trick to allow calling private methods within the closure on PHP 5.3.
				return $that->closureGetWorkingCopyUrlOnOldFormatWorkingCopy();
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
		$this->assertEquals(self::DUMMY_REPO, $actual);
	}

	public function closureGetWorkingCopyUrlOnOldFormatWorkingCopy()
	{
		$this->_expectCommand(
			"svn --non-interactive info --xml '/path/to/working-copy'",
			$this->getFixture('svn_info_16.xml')
		);

		return true;
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
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetRelativePathAutomaticCachingForUrls($given_repository_url, $used_repository_url)
	{
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $used_repository_url . "'";
		$raw_command_output = $this->getFixture('svn_info_remote.xml');

		$this->_cacheManager->getCache('repository.com/command:' . $raw_command, null, '1 year')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('repository.com/command:' . $raw_command, $raw_command_output, null, '1 year')
			->shouldBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b');

		$this->_expectCommand($raw_command, $raw_command_output);

		$this->assertEquals('/path/to/project', $repository_connector->getRelativePath($given_repository_url));
	}

	public function testGetRelativePathAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $path . "'";
		$raw_command_output = $this->getFixture('svn_info_16.xml');

		$this->_cacheManager->getCache('misc/command:' . $raw_command, null, '1 year')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('misc/command:' . $raw_command, $raw_command_output, null, '1 year')
			->shouldBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b');

		$this->_expectCommand($raw_command, $raw_command_output);

		$this->assertEquals('/path/to/project', $repository_connector->getRelativePath($path));
	}

	/**
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetRootUrlAutomaticCachingForUrls($given_repository_url, $used_repository_url)
	{
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $used_repository_url . "'";
		$raw_command_output = $this->getFixture('svn_info_remote.xml');

		$this->_cacheManager->getCache('repository.com/command:' . $raw_command, null, '1 year')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('repository.com/command:' . $raw_command, $raw_command_output, null, '1 year')
			->shouldBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b');

		$this->_expectCommand($raw_command, $raw_command_output);

		$this->assertEquals('svn://repository.com', $repository_connector->getRootUrl($given_repository_url));
	}

	public function testGetRootUrlAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $path . "'";
		$raw_command_output = $this->getFixture('svn_info_16.xml');

		$this->_cacheManager->getCache('misc/command:' . $raw_command, null, '1 year')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('misc/command:' . $raw_command, $raw_command_output, null, '1 year')
			->shouldBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b');

		$this->_expectCommand($raw_command, $raw_command_output);

		$this->assertEquals('svn://repository.com', $repository_connector->getRootUrl($path));
	}

	/**
	 * @dataProvider isRefRootDataProvider
	 */
	public function testIsRefRoot($path, $result)
	{
		$this->assertSame($result, $this->_repositoryConnector->isRefRoot($path));
	}

	public function isRefRootDataProvider()
	{
		return array(
			array('/projects/project_a/trunk/', true),
			array('/projects/project_a/trunk/sub-folder/file.tpl', false),
			array('/projects/project_a/trunk/sub-folder', false),
			array('/projects/project_a/branches/branch-name/', true),
			array('/projects/project_a/branches/branch-name/another_file.php', false),
			array('/projects/project_a/branches/', false),
			array('/projects/project_a/tags/tag-name/', true),
			array('/projects/project_a/tags/tag-name/another_file.php', false),
			array('/projects/project_a/tags/', false),
			array('/projects/project_a/unknowns/unknown-name/another_file.php', false),
			array('/projects/project_a/releases/release-name/', true),
			array('/projects/project_a/releases/release-name/another_file.php', false),
			array('/projects/project_a/releases/', false),
		);
	}

	/**
	 * @dataProvider getRefByPathDataProvider
	 */
	public function testGetRefByPath($path, $ref)
	{
		$this->assertSame($ref, $this->_repositoryConnector->getRefByPath($path));
	}

	public function getRefByPathDataProvider()
	{
		return array(
			array('/projects/project_a/trunk/sub-folder/file.tpl', 'trunk'),
			array('/projects/project_a/trunk/sub-folder', 'trunk'),
			array('/projects/project_a/branches/branch-name/another_file.php', 'branches/branch-name'),
			array('/projects/project_a/branches/', false),
			array('/projects/project_a/tags/tag-name/another_file.php', 'tags/tag-name'),
			array('/projects/project_a/tags/', false),
			array('/projects/project_a/unknowns/unknown-name/another_file.php', false),
			array('/projects/project_a/releases/release-name/another_file.php', 'releases/release-name'),
			array('/projects/project_a/releases/', false),
		);
	}

	/**
	 * @dataProvider getLastRevisionWithoutAutomaticDataProvider
	 */
	public function testGetLastRevisionWithoutAutomaticCaching($cache_duration)
	{
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . self::DUMMY_REPO . "'";

		$this->_cacheManager->getCache('command:' . $raw_command, null)->shouldNotBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b', $cache_duration);

		$this->_expectCommand(
			$raw_command,
			$this->getFixture('svn_info_remote.xml')
		);

		$this->assertEquals(100, $repository_connector->getLastRevision(self::DUMMY_REPO));
	}

	public function getLastRevisionWithoutAutomaticDataProvider()
	{
		return array(
			array(''),
			array(0),
			array('0 seconds'),
			array('0 minutes'),
		);
	}

	/**
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetLastRevisionWithAutomaticCaching($given_repository_url, $used_repository_url)
	{
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $used_repository_url . "'";
		$raw_command_output = $this->getFixture('svn_info_remote.xml');

		$this->_cacheManager->getCache('repository.com/command:' . $raw_command, null, '1 minute')->willReturn(null)->shouldBeCalled();
		$this->_cacheManager
			->setCache('repository.com/command:' . $raw_command, $raw_command_output, null, '1 minute')
			->shouldBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b', '1 minute');

		$this->_expectCommand(
			$raw_command,
			$raw_command_output
		);

		$this->assertEquals(100, $repository_connector->getLastRevision($given_repository_url));
	}

	public function testGetLastRevisionNoAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$raw_command = "svn --non-interactive --username a --password b info --xml '" . $path . "'";

		$this->_cacheManager->getCache('command:' . $raw_command, null)->shouldNotBeCalled();

		$repository_connector = $this->_createRepositoryConnector('a', 'b', '1 minute');

		$this->_expectCommand(
			$raw_command,
			$this->getFixture('svn_info_16.xml')
		);

		$this->assertEquals(100, $repository_connector->getLastRevision($path));
	}

	/**
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testRemoveCredentials($given_repository_url, $used_repository_url)
	{
		$this->assertEquals(
			$used_repository_url,
			$this->_repositoryConnector->removeCredentials($given_repository_url)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Unable to remove credentials from "/path/to/working-copy" path.
	 */
	public function testRemoveCredentialsFromPath()
	{
		$this->_repositoryConnector->removeCredentials('/path/to/working-copy');
	}

	public function svnInfoBasedMethodDataProvider()
	{
		return array(
			'repo without username' => array(
				'svn://repository.com/path/to/project',
				'svn://repository.com/path/to/project',
			),
			'repo with username' => array(
				'svn://user@repository.com/path/to/project',
				'svn://repository.com/path/to/project',
			),
		);
	}

	/**
	 * @dataProvider getProjectUrlDataProvider
	 */
	public function testGetProjectUrl($repository_url)
	{
		$this->assertEquals(
			'svn://user@domain.com/path/to/project',
			$this->_repositoryConnector->getProjectUrl($repository_url)
		);
	}

	public function getProjectUrlDataProvider()
	{
		return array(
			// Root.
			'root' => array('svn://user@domain.com/path/to/project'),
			'trunk root' => array('svn://user@domain.com/path/to/project/trunk'),
			'branch root' => array('svn://user@domain.com/path/to/project/branches/blue'),
			'tag root' => array('svn://user@domain.com/path/to/project/tags/blue'),
			'release root' => array('svn://user@domain.com/path/to/project/releases/blue'),

			// Sub folder.
			'trunk sub-folder' => array('svn://user@domain.com/path/to/project/trunk/sub-folder'),
			'branch sub-folder' => array('svn://user@domain.com/path/to/project/branches/blue/sub-folder'),
			'tag sub-folder' => array('svn://user@domain.com/path/to/project/tags/blue/sub-folder'),
			'release sub-folder' => array('svn://user@domain.com/path/to/project/releases/blue/sub-folder'),
		);
	}

	/**
	 * @dataProvider getWorkingCopyConflictsDataProvider
	 */
	public function testGetWorkingCopyConflicts($fixture, $expected)
	{
		$this->_expectCommand(
			"svn --non-interactive status --xml '/path/to/working-copy'",
			$this->getFixture($fixture)
		);

		$this->assertEquals(
			$expected,
			$this->_repositoryConnector->getWorkingCopyConflicts('/path/to/working-copy')
		);
	}

	public function getWorkingCopyConflictsDataProvider()
	{
		return array(
			'with conflicts' => array('svn_status_with_conflicts_16.xml', array('.', 'admin', 'admin/index.php')),
			'without conflicts' => array('svn_status_with_changelists_16.xml', array()),
		);
	}

	public function testGetWorkingCopyStatusWithoutChangelist()
	{
		$this->_expectCommand(
			"svn --non-interactive status --xml '/path/to/working-copy'",
			$this->getFixture('svn_status_with_changelist_16.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false),
				'admin' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => true),
				'admin/index.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false),
				'admin/system_presets/simple/users_u.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy')
		);
	}

	public function testGetWorkingCopyStatusWithChangelist()
	{
		$this->_expectCommand(
			"svn --non-interactive status --xml '/path/to/working-copy'",
			$this->getFixture('svn_status_with_changelist_16.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false),
				'admin' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => true),
				'admin/system_presets/simple/users_u.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy', 'cl one')
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "cl missing" changelist doens't exist.
	 */
	public function testGetWorkingCopyStatusWithNonExistingChangelist()
	{
		$this->_expectCommand(
			"svn --non-interactive status --xml '/path/to/working-copy'",
			$this->getFixture('svn_status_with_changelist_16.xml')
		);

		$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy', 'cl missing');
	}

	/**
	 * @dataProvider getWorkingCopyChangelistsDataProvider
	 */
	public function testGetWorkingCopyChangelists($fixture, $expected)
	{
		$this->_expectCommand(
			"svn --non-interactive status --xml '/path/to/working-copy'",
			$this->getFixture($fixture)
		);

		$this->assertEquals(
			$expected,
			$this->_repositoryConnector->getWorkingCopyChangelists('/path/to/working-copy')
		);
	}

	public function getWorkingCopyChangelistsDataProvider()
	{
		return array(
			'with changelists' => array('svn_status_with_changelists_16.xml', array('cl one', 'cl two')),
			'without changelists' => array('svn_status_without_changelists_16.xml', array()),
		);
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
	 * @param string $username                     Username.
	 * @param string $password                     Password.
	 * @param string $last_revision_cache_duration Last revision cache duration.
	 *
	 * @return Connector
	 */
	private function _createRepositoryConnector($username, $password, $last_revision_cache_duration = '10 minutes')
	{
		$this->_configEditor->get('repository-connector.username')->willReturn($username)->shouldBeCalled();
		$this->_configEditor->get('repository-connector.password')->willReturn($password)->shouldBeCalled();
		$this->_configEditor
			->get('repository-connector.last-revision-cache-duration')
			->willReturn($last_revision_cache_duration)
			->shouldBeCalled();

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
