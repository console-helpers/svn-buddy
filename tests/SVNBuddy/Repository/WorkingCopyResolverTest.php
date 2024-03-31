<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class WorkingCopyResolverTest extends AbstractTestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $connector;

	/**
	 * Working copy resolver.
	 *
	 * @var WorkingCopyResolver
	 */
	protected $workingCopyResolver;

	/**
	 * Temporary folder.
	 *
	 * @var string
	 */
	protected $tempFolder;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->connector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');
		$this->workingCopyResolver = new WorkingCopyResolver($this->connector->reveal());
	}

	/**
	 * @after
	 * @return void
	 */
	protected function teardownTest()
	{
		if ( !empty($this->tempFolder) && file_exists($this->tempFolder) ) {
			rmdir($this->tempFolder);
		}
	}

	public function testGetWorkingCopyUrlFromUrl()
	{
		$this->connector->isUrl('svn://user:password@repository.com')->willReturn(true);
		$this->connector->removeCredentials('svn://user:password@repository.com')->willReturn('svn://repository.com');
		$this->connector->isUrl('svn://repository.com')->willReturn(true);
		$this->connector->getWorkingCopyUrl('svn://repository.com')->willReturnArgument(0);

		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyUrl('svn://user:password@repository.com'),
			'Cache Miss'
		);
		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyUrl('svn://user:password@repository.com'),
			'Cache Hit'
		);
	}

	public function testGetWorkingCopyPathFromUrl()
	{
		$this->connector->isUrl('svn://user:password@repository.com')->willReturn(true);
		$this->connector->removeCredentials('svn://user:password@repository.com')->willReturn('svn://repository.com');
		$this->connector->isUrl('svn://repository.com')->willReturn(true);

		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyPath('svn://user:password@repository.com'),
			'Cache Miss'
		);
		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyPath('svn://user:password@repository.com'),
			'Cache Hit'
		);
	}

	public function testGetWorkingCopyUrlFromPath()
	{
		$this->createTempFolder();

		$this->connector->isUrl($this->tempFolder)->willReturn(false);
		$this->connector->isWorkingCopy($this->tempFolder)->willReturn(true);
		$this->connector->getWorkingCopyUrl($this->tempFolder)->willReturn('svn://repository.com');

		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyUrl($this->tempFolder),
			'Cache Miss'
		);
		$this->assertEquals(
			'svn://repository.com',
			$this->workingCopyResolver->getWorkingCopyUrl($this->tempFolder),
			'Cache Hit'
		);
	}

	public function testGetWorkingCopyPathFromPath()
	{
		$this->createTempFolder();

		$this->connector->isUrl($this->tempFolder)->willReturn(false);
		$this->connector->isWorkingCopy($this->tempFolder)->willReturn(true);

		$this->assertEquals(
			$this->tempFolder,
			$this->workingCopyResolver->getWorkingCopyPath($this->tempFolder),
			'Cache Miss'
		);
		$this->assertEquals(
			$this->tempFolder,
			$this->workingCopyResolver->getWorkingCopyPath($this->tempFolder),
			'Cache Hit'
		);
	}

	public function testGetWorkingCopyPathFromNonWorkingCopy()
	{
		$this->createTempFolder();

		$this->connector->isUrl($this->tempFolder)->willReturn(false);
		$this->connector->isWorkingCopy($this->tempFolder)->willReturn(false);

		$this->expectException('LogicException');
		$this->expectExceptionMessage('The "' . $this->tempFolder . '" isn\'t a working copy.');

		$this->workingCopyResolver->getWorkingCopyPath($this->tempFolder);
	}

	public function testGetWorkingCopyPathFromDeletedPath()
	{
		$this->createTempFolder();

		$this->connector->isUrl($this->tempFolder . '/deleted')->willReturn(false);
		$this->connector->isUrl($this->tempFolder)->willReturn(false);
		$this->connector->isWorkingCopy($this->tempFolder)->willReturn(true);

		$this->assertEquals(
			$this->tempFolder,
			$this->workingCopyResolver->getWorkingCopyPath($this->tempFolder . '/deleted'),
			'Cache Miss'
		);
		$this->assertEquals(
			$this->tempFolder,
			$this->workingCopyResolver->getWorkingCopyPath($this->tempFolder . '/deleted'),
			'Cache Hit'
		);
	}

	/**
	 * Creates temporary home directory.
	 *
	 * @return void
	 */
	protected function createTempFolder()
	{
		$temp_file = tempnam(sys_get_temp_dir(), 'sb_');
		unlink($temp_file);
		mkdir($temp_file);

		$this->tempFolder = $temp_file;
	}

}
