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
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Repository\Connector\CommandFactory;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Command;

class ConnectorTest extends AbstractTestCase
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
	 * Command factory.
	 *
	 * @var ObjectProphecy
	 */
	private $_commandFactory;

	/**
	 * Revision list parser.
	 *
	 * @var ObjectProphecy
	 */
	private $_revisionListParser;

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->_configEditor = $this->prophesize(ConfigEditor::class);
		$this->_io = $this->prophesize(ConsoleIO::class);
		$this->_commandFactory = $this->prophesize(CommandFactory::class);
		$this->_revisionListParser = $this->prophesize(RevisionListParser::class);

		// To get nice exception back when unexpected command is executed.
		$this->_commandFactory
			->getCommand(Argument::any(), Argument::any())
			->will(function (array $args) {
				throw new \LogicException(\sprintf(
					'The getCommand(%s, %s) call wasn\'t expected.',
					var_export($args[0], true),
					var_export($args[1], true)
				));
			});

		$this->_repositoryConnector = $this->_createRepositoryConnector();
	}

	/**
	 * @dataProvider getCommandWithCachingDataProvider
	 */
	public function testGetCommandWithCaching($duration, $overwrite)
	{
		$command = $this->_expectCommand('info', array(), 'OK');

		if ( $duration !== null ) {
			$command->setCacheDuration($duration)->shouldBeCalled();
		}

		if ( $overwrite !== null ) {
			$command->setCacheOverwrite($overwrite)->shouldBeCalled();
		}

		$this->_repositoryConnector->withCache($duration, $overwrite)->getCommand('info')->run();
	}

	public static function getCommandWithCachingDataProvider()
	{
		return array(
			'duration - enabled, overwrite - enabled' => array(100, true),
			'duration - enabled, overwrite - disabled 1' => array(100, false),
			'duration - enabled, overwrite - disabled 2' => array(100, null),

			'duration - disabled, overwrite - enabled' => array(null, true),
			'duration - disabled, overwrite - disabled 1' => array(null, false),
			'duration - disabled, overwrite - disabled 2' => array(null, null),
		);
	}

	public function testGetProperty()
	{
		$this->_expectCommand('propget', array('prop-name', 'the/path'), 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('prop-name', 'the/path')
		);
	}

	public function testGetPropertyWithRevision()
	{
		$this->_expectCommand('propget', array('prop-name', 'the/path', '--revision', 5), 'OK');

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getProperty('prop-name', 'the/path', 5)
		);
	}

	public function testGetNonExistingPropertyOnSubversion18()
	{
		$this->_expectCommand('propget', array('prop-name', 'the/path', '--revision', 5), '');

		$this->assertSame(
			'',
			$this->_repositoryConnector->getProperty('prop-name', 'the/path', 5)
		);
	}

	public function testGetNonExistingPropertyOnSubversion19()
	{
		$this->_expectCommand(
			'propget',
			array('prop-name', 'the/path', '--revision', 5),
			null,
			'A problem occurred; see other errors for details',
			RepositoryCommandException::SVN_ERR_BASE
		);

		$this->assertSame(
			'',
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
	public function testGetWorkingCopyUrlFromPath($raw_command_output, $path, $url)
	{
		if ( strpos($path, '@') !== false ) {
			$sub_command = 'info';
			$arguments = array('--xml', $path . '@');
		}
		else {
			$sub_command = 'info';
			$arguments = array('--xml', $path);
		}

		$command = $this->_expectCommand($sub_command, $arguments, $raw_command_output);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$actual = $this->_repositoryConnector->getWorkingCopyUrl($path);
		$this->assertEquals($url, $actual);
	}

	public static function svnInfoDataProvider()
	{
		return array(
			'svn1.6_wc_root_with_peg' => array(self::getFixture('svn_info_peg_16.xml'), '/path/to/working-c@py', self::DUMMY_REPO),
			'svn1.6_wc_root' => array(self::getFixture('svn_info_16.xml'), '/path/to/working-copy', self::DUMMY_REPO),
			'svn1.7_wc_root' => array(self::getFixture('svn_info_17.xml'), '/path/to/working-copy', self::DUMMY_REPO),
			'svn1.6_wc_sub_folder' => array(self::getFixture('svn_info_sub_folder_16.xml'), '/path/to/working-copy/sub-folder', self::DUMMY_REPO . '/sub-folder'),
			'svn1.8_wc_sub_folder' => array(self::getFixture('svn_info_sub_folder_18.xml'), '/path/to/working-copy/sub-folder', self::DUMMY_REPO . '/sub-folder'),
		);
	}

	public function testGetWorkingCopyUrlWithBrokenSvnInfo()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The directory "/path/to/working-copy" not found in "svn info" command results.');

		$command = $this->_expectCommand(
			'info',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_info_broken.xml')
		);
		$command->setCacheDuration('1 year')->shouldBeCalled();

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

		$command = $this->_expectCommand(
			'info',
			array('--xml', '/path/to/working-copy'),
			'',
			'error message',
			RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED
		);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$this->_expectCommand('upgrade', array('/path/to/working-copy'), 'OK');

		$actual = $this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
		$this->assertEquals(self::DUMMY_REPO, $actual);
	}

	public function closureGetWorkingCopyUrlOnOldFormatWorkingCopy()
	{
		$command = $this->_expectCommand(
			'info',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_info_16.xml')
		);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		return true;
	}

	public function testGetWorkingCopyUrlWithUnknownError()
	{
		$command = $this->_expectCommand(
			'info',
			array('--xml', '/path/to/working-copy'),
			'',
			'error message',
			555
		);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$exception_msg = <<<MESSAGE
Command:
stub command
Error #555:
error message
MESSAGE;
		$this->expectException(RepositoryCommandException::class);
		$this->expectExceptionMessage($exception_msg);

		$this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
	}

	public function testGetWorkingCopyUrlOnOldFormatWorkingCopyAndUpgradeRejected()
	{
		$this->_io->writeln(array('', '<error>error message</error>', ''))->shouldBeCalled();
		$this->_io
			->askConfirmation('Run "svn upgrade"', false)
			->willReturn(false)
			->shouldBeCalled();

		$command = $this->_expectCommand(
			'info',
			array('--xml', '/path/to/working-copy'),
			'',
			'error message',
			RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED
		);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$exception_msg = <<<MESSAGE
Command:
stub command
Error #%d:
error message
MESSAGE;

		$this->expectException(RepositoryCommandException::class);
		$this->expectExceptionMessage(sprintf(
			$exception_msg,
			RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED
		));

		$this->_repositoryConnector->getWorkingCopyUrl('/path/to/working-copy');
	}

	/**
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetRelativePathAutomaticCachingForUrls($given_repository_url, $used_repository_url)
	{
		$raw_command_output = self::getFixture('svn_info_remote.xml');

		$repository_connector = $this->_createRepositoryConnector();

		$command = $this->_expectCommand('info', array('--xml', $used_repository_url), $raw_command_output);
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$this->assertEquals('/path/to/project', $repository_connector->getRelativePath($given_repository_url));
	}

	public function testGetRelativePathAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$repository_connector = $this->_createRepositoryConnector();

		$command = $this->_expectCommand('info', array('--xml', $path), self::getFixture('svn_info_16.xml'));
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$this->assertEquals('/path/to/project', $repository_connector->getRelativePath($path));
	}

	/**
	 * @dataProvider svnInfoBasedMethodDataProvider
	 */
	public function testGetRootUrlAutomaticCachingForUrls($given_repository_url, $used_repository_url)
	{
		$repository_connector = $this->_createRepositoryConnector();

		$command = $this->_expectCommand('info', array('--xml', $used_repository_url), self::getFixture('svn_info_remote.xml'));
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$this->assertEquals('svn://repository.com', $repository_connector->getRootUrl($given_repository_url));
	}

	public function testGetRootUrlAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$repository_connector = $this->_createRepositoryConnector();

		$command = $this->_expectCommand('info', array('--xml', $path), self::getFixture('svn_info_16.xml'));
		$command->setCacheDuration('1 year')->shouldBeCalled();

		$this->assertEquals('svn://repository.com', $repository_connector->getRootUrl($path));
	}

	/**
	 * @dataProvider isRefRootDataProvider
	 */
	public function testIsRefRoot($path, $result)
	{
		$this->assertSame($result, $this->_repositoryConnector->isRefRoot($path));
	}

	public static function isRefRootDataProvider()
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

	public static function getRefByPathDataProvider()
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
		$repository_connector = $this->_createRepositoryConnector($cache_duration);

		$command = $this->_expectCommand(
			'info',
			array('--xml', self::DUMMY_REPO),
			self::getFixture('svn_info_remote.xml')
		);
		$command->setCacheDuration(0)->shouldBeCalled();

		$this->assertEquals(100, $repository_connector->getLastRevision(self::DUMMY_REPO));
	}

	public static function getLastRevisionWithoutAutomaticDataProvider()
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
		$repository_connector = $this->_createRepositoryConnector('1 minute');

		$command = $this->_expectCommand(
			'info',
			array('--xml', $used_repository_url),
			self::getFixture('svn_info_remote.xml')
		);
		$command->setCacheDuration('1 minute')->shouldBeCalled();

		$this->assertEquals(100, $repository_connector->getLastRevision($given_repository_url));
	}

	public function testGetLastRevisionNoAutomaticCachingForPaths()
	{
		$path = '/path/to/working-copy';
		$repository_connector = $this->_createRepositoryConnector('1 minute');

		$command = $this->_expectCommand(
			'info',
			array('--xml', $path),
			self::getFixture('svn_info_16.xml')
		);
		$command->setCacheDuration(Argument::any())->shouldNotBeCalled();

		$this->assertEquals(100, $repository_connector->getLastRevision($path));
	}

	/**
	 * @dataProvider getLastRevisionOnRepositoryRootDataProvider
	 */
	public function testGetLastRevisionOnRepositoryRoot($fixture)
	{
		$raw_command_output = self::getFixture($fixture);

		$repository_connector = $this->_createRepositoryConnector('1 minute');

		$command = $this->_expectCommand(
			'info',
			array('--xml', 'svn://repository.com'),
			$raw_command_output
		);
		$command->setCacheDuration('1 minute')->shouldBeCalled();

		$this->assertEquals(100, $repository_connector->getLastRevision('svn://repository.com'));
	}

	public static function getLastRevisionOnRepositoryRootDataProvider()
	{
		return array(
			'svn_1.6' => array('svn_info_repository_root_remote_16.xml'),
			'svn_1.8' => array('svn_info_repository_root_remote_18.xml'),
		);
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

	public function testRemoveCredentialsFromPath()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('Unable to remove credentials from "/path/to/working-copy" path.');

		$this->_repositoryConnector->removeCredentials('/path/to/working-copy');
	}

	public static function svnInfoBasedMethodDataProvider()
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

	public static function getProjectUrlDataProvider()
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
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture($fixture)
		);

		$this->assertEquals(
			$expected,
			$this->_repositoryConnector->getWorkingCopyConflicts('/path/to/working-copy')
		);
	}

	public static function getWorkingCopyConflictsDataProvider()
	{
		return array(
			'with conflicts' => array('svn_status_with_conflicts_16.xml', array('.', 'admin', 'admin/index.php')),
			'without conflicts' => array('svn_status_with_changelists_16.xml', array()),
		);
	}

	/**
	 * @dataProvider getWorkingCopyMissingDataProvider
	 */
	public function testGetWorkingCopyMissing($fixture, $expected)
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture($fixture)
		);

		$this->assertEquals(
			$expected,
			$this->_repositoryConnector->getWorkingCopyMissing('/path/to/working-copy')
		);
	}

	public static function getWorkingCopyMissingDataProvider()
	{
		return array(
			'with conflicts' => array('svn_status_with_missing_16.xml', array('themes', 'admin/README')),
			'without conflicts' => array('svn_info_16.xml', array()),
		);
	}

	public function testGetWorkingCopyStatusWithoutChangelist()
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_changelist_16.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false, 'copied' => false),
				'admin' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => true, 'copied' => false),
				'admin/index.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
				'admin/system_presets/simple/users_u.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy')
		);
	}

	public function testGetWorkingCopyStatusWithoutChangelistAndWithoutExclusions()
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_changelist_16.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false, 'copied' => false),
				'admin' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => true, 'copied' => false),
				'admin/index.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
				'new.txt' => array('item' => 'unversioned', 'props' => 'none', 'tree-conflicted' => false, 'copied' => false),
				'themes/default' => array('item' => 'external', 'props' => 'none', 'tree-conflicted' => false, 'copied' => false),
				'admin/system_presets/simple/users_u.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy', null, array())
		);
	}

	public function testGetWorkingCopyStatusWithChangelist()
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_changelist_16.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false, 'copied' => false),
				'admin' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => true, 'copied' => false),
				'admin/system_presets/simple/users_u.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy', 'cl one')
		);
	}

	public function testGetWorkingCopyStatusWithNonExistingChangelist()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "cl missing" changelist doens\'t exist.');

		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_changelist_16.xml')
		);

		$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy', 'cl missing');
	}

	public function testGetWorkingCopyStatusWithNoneProperties()
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_props_eq_none18.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false, 'copied' => false),
				'modules/custom/units/helpers/helpers_config.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
				'modules/custom/units/sections/e_product_eh.php' => array('item' => 'modified', 'props' => 'none', 'tree-conflicted' => false, 'copied' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy')
		);
	}

	public function testGetWorkingCopyStatusWithCopiedPaths()
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture('svn_status_with_copied18.xml')
		);

		$this->assertSame(
			array(
				'.' => array('item' => 'normal', 'props' => 'modified', 'tree-conflicted' => false, 'copied' => false),
				'modules/custom/admin_templates/copied_folder' => array('item' => 'added', 'props' => 'none', 'tree-conflicted' => false, 'copied' => true),
				'modules/custom/units/helpers/helpers_config.php' => array('item' => 'modified', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => false),
				'modules/custom/units/helpers/import/product_image_import_helper.php' => array('item' => 'added', 'props' => 'normal', 'tree-conflicted' => false, 'copied' => true),
				'modules/custom/units/sections/e_product_eh.php' => array('item' => 'modified', 'props' => 'none', 'tree-conflicted' => false, 'copied' => false),
			),
			$this->_repositoryConnector->getWorkingCopyStatus('/path/to/working-copy')
		);
	}

	/**
	 * @dataProvider getWorkingCopyChangelistsDataProvider
	 */
	public function testGetWorkingCopyChangelists($fixture, $expected)
	{
		$this->_expectCommand(
			'status',
			array('--xml', '/path/to/working-copy'),
			self::getFixture($fixture)
		);

		$this->assertEquals(
			$expected,
			$this->_repositoryConnector->getWorkingCopyChangelists('/path/to/working-copy')
		);
	}

	public static function getWorkingCopyChangelistsDataProvider()
	{
		return array(
			'with changelists' => array('svn_status_with_changelists_16.xml', array('cl one', 'cl two')),
			'without changelists' => array('svn_status_without_changelists_16.xml', array()),
		);
	}

	/**
	 * @dataProvider testGetMergedRevisionChangesWithoutChangesDataProvider
	 */
	public function testGetMergedRevisionChangesWithoutChanges($regular_or_reverse)
	{
		$merge_info = '/projects/project-name/trunk:10,15' . PHP_EOL;
		$this->_expectCommand(
			'propget',
			array('svn:mergeinfo', '/path/to/working-copy', '--revision', 'BASE'),
			$merge_info
		);
		$this->_expectCommand(
			'propget',
			array('svn:mergeinfo', '/path/to/working-copy'),
			$merge_info
		);

		$this->assertEmpty(
			$this->_repositoryConnector->getMergedRevisionChanges('/path/to/working-copy', $regular_or_reverse)
		);
	}

	public static function testGetMergedRevisionChangesWithoutChangesDataProvider()
	{
		return array(
			'Merged revisions' => array(true),
			'Reverse-merged revisions' => array(false),
		);
	}

	/**
	 * @dataProvider testGetMergedRevisionChangesWithChangesDataProvider
	 */
	public function testGetMergedRevisionChangesWithChanges($regular_or_reverse, $base_merged, $wc_merged)
	{
		$this->_expectCommand(
			'propget',
			array('svn:mergeinfo', '/path/to/working-copy', '--revision', 'BASE'),
			$base_merged
		);
		$this->_expectCommand(
			'propget',
			array('svn:mergeinfo', '/path/to/working-copy'),
			$wc_merged
		);

		$this->_revisionListParser->expandRanges(Argument::cetera())->willReturnArgument(0);

		$this->assertSame(
			array(
				'/projects/project-name/trunk' => array('18', '33'),
				'/projects/project-name/branches/branch-name' => array('4'),
			),
			$this->_repositoryConnector->getMergedRevisionChanges('/path/to/working-copy', $regular_or_reverse)
		);
	}

	public static function testGetMergedRevisionChangesWithChangesDataProvider()
	{
		return array(
			'Merged revisions' => array(
				true,
				'/projects/project-name/trunk:10,15' . PHP_EOL,
				'/projects/project-name/trunk:10,15,18,33' . PHP_EOL .
				'/projects/project-name/branches/branch-name:4' . PHP_EOL,
			),
			'Reverse-merged revisions' => array(
				false,
				'/projects/project-name/trunk:10,15,18,33' . PHP_EOL .
				'/projects/project-name/branches/branch-name:4' . PHP_EOL,
				'/projects/project-name/trunk:10,15' . PHP_EOL,
			),
		);
	}

	public function testGetFileContent()
	{
		$command = $this->_expectCommand('cat', array('/path/to/working-copy/file.php', '--revision', 100), 'OK');
		$command->setCacheDuration(Connector::SVN_CAT_CACHE_DURATION)->shouldBeCalled();

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getFileContent('/path/to/working-copy/file.php', 100)
		);

		$command = $this->_expectCommand('cat', array('/path/to/working-copy/file.php', '--revision', 'HEAD'), 'OK');
		$command->setCacheDuration(Connector::SVN_CAT_CACHE_DURATION)->shouldBeCalled();

		$this->assertEquals(
			'OK',
			$this->_repositoryConnector->getFileContent('/path/to/working-copy/file.php', 'HEAD')
		);
	}

	/**
	 * Sets expectation for specific command.
	 *
	 * @param string      $sub_command Sub command.
	 * @param array       $arguments   Arguments.
	 * @param string      $output      Output.
	 * @param string|null $error_msg   Error msg.
	 * @param integer     $error_code  Error code.
	 *
	 * @return ObjectProphecy
	 */
	private function _expectCommand($sub_command, array $arguments, $output, $error_msg = null, $error_code = 0)
	{
		$command = $this->prophesize(Command::class);

		if ( $sub_command === 'upgrade' ) {
			$run_expectation = $command->runLive()->shouldBeCalled();
		}
		else {
			$run_expectation = $command->run()->shouldBeCalled();
		}

		if ( isset($error_code) && isset($error_msg) ) {
			$run_expectation->willThrow(
				new RepositoryCommandException('stub command', 'svn: E' . $error_code . ': ' . $error_msg)
			);
		}
		else {
			if ( in_array('--xml', $arguments) ) {
				$output = simplexml_load_string($output);
			}

			$run_expectation->willReturn($output);
		}

		$this->_commandFactory
			->getCommand($sub_command, $arguments)
			->willReturn($command->reveal())
			->shouldBeCalled();

		return $command;
	}

	/**
	 * Creates repository connector.
	 *
	 * @param string $last_revision_cache_duration Last revision cache duration.
	 *
	 * @return Connector
	 */
	private function _createRepositoryConnector($last_revision_cache_duration = '10 minutes')
	{
		$this->_configEditor
			->get('repository-connector.last-revision-cache-duration')
			->willReturn($last_revision_cache_duration)
			->shouldBeCalled();

		return new Connector(
			$this->_configEditor->reveal(),
			$this->_commandFactory->reveal(),
			$this->_io->reveal(),
			$this->_revisionListParser->reveal()
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
	protected static function getFixture($name)
	{
		$fixture_filename = __DIR__ . '/fixtures/' . $name;

		if ( !file_exists($fixture_filename) ) {
			throw new \InvalidArgumentException('The "' . $name . '" fixture does not exist.');
		}

		return file_get_contents($fixture_filename);
	}

}
