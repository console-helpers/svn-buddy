<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/console-kit
 */

namespace Tests\ConsoleHelpers\ConsoleKit;


use ConsoleHelpers\ConsoleKit\WorkingDirectory;

class WorkingDirectoryTest extends WorkingDirectoryAwareTestCase
{

	/**
	 * @expectedException \ConsoleHelpers\ConsoleKit\Exception\ApplicationException
	 * @expectedExceptionMessage The $sub_folder is a path or empty.
	 * @dataProvider incorrectSubFolderDataProvider
	 */
	public function testCreationWithIncorrectSubFolder($sub_folder)
	{
		new WorkingDirectory($sub_folder);
	}

	public function incorrectSubFolderDataProvider()
	{
		return array(
			'empty sub-folder' => array(''),
			'path sub-folder' => array('a/b'),
		);
	}

	public function testWorkingDirectoryCreation()
	{
		$expected_working_directory = $this->getExpectedWorkingDirectory();

		$actual_working_directory = $this->getWorkingDirectory();
		$this->assertEquals($expected_working_directory, $actual_working_directory);
		$this->assertFileExists($expected_working_directory);

		// If directory is created, when it exists, them this would trigger a warning.
		$this->getWorkingDirectory();
	}

	/**
	 * @expectedException \ConsoleHelpers\ConsoleKit\Exception\ApplicationException
	 * @expectedExceptionMessage The HOME environment variable must be set to run correctly
	 */
	public function testBrokenLinuxEnvironment()
	{
		putenv('HOME=');
		$this->getWorkingDirectory();
	}

	/**
	 * @runInSeparateProcess
	 * @expectedException \ConsoleHelpers\ConsoleKit\Exception\ApplicationException
	 * @expectedExceptionMessage The APPDATA environment variable must be set to run correctly
	 */
	public function testBrokenWindowsEnvironment()
	{
		putenv('HOME=');
		define('PHP_WINDOWS_VERSION_MAJOR', 5);

		$this->getWorkingDirectory();
	}

	/**
	 * Returns correct working directory.
	 *
	 * @return string
	 */
	protected function getExpectedWorkingDirectory()
	{
		$sub_folder = array_key_exists('working_directory', $_SERVER) ? $_SERVER['working_directory'] : '';

		return getenv('HOME') . '/' . $sub_folder;
	}

}
