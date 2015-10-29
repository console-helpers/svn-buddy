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

abstract class WorkingDirectoryAwareTestCase extends \PHPUnit_Framework_TestCase
{

	protected function setUp()
	{
		parent::setUp();

		if ( $this->requireWorkingDirectory() ) {
			$this->_createTempHomeDirectory();
		}
	}

	/**
	 * Determines if test require config.
	 *
	 * @return boolean
	 */
	protected function requireWorkingDirectory()
	{
		return true;
	}

	/**
	 * Returns working directory.
	 *
	 * @return string
	 */
	protected function getWorkingDirectory()
	{
		$sub_folder = array_key_exists('working_directory', $_SERVER) ? $_SERVER['working_directory'] : '';

		if ( !strlen($sub_folder) ) {
			$this->fail('Please set "working_directory" environment variable before calling ' . __METHOD__ . '.');
		}

		$working_directory = new WorkingDirectory($sub_folder);

		return $working_directory->get();
	}

	protected function tearDown()
	{
		parent::tearDown();

		$this->_restoreHomeDirectory();
	}

	/**
	 * Creates temporary home directory.
	 *
	 * @return void
	 */
	private function _createTempHomeDirectory()
	{
		$temp_file = tempnam(sys_get_temp_dir(), 'sb_');
		unlink($temp_file);
		mkdir($temp_file);

		putenv('HOME=' . $temp_file);
	}

	/**
	 * Restores original home directory and removes temporary one.
	 *
	 * @return void
	 * @throws \LogicException When original home directory is empty.
	 */
	private function _restoreHomeDirectory()
	{
		$original_home_directory = $_SERVER['HOME'];

		if ( empty($original_home_directory) ) {
			throw new \LogicException('Unable to restore empty home directory.');
		}

		$current_home_directory = getenv('HOME');

		if ( $current_home_directory && $current_home_directory != $original_home_directory ) {
			shell_exec('rm -Rf ' . escapeshellarg($current_home_directory));
			putenv('HOME=' . $original_home_directory);
		}
	}

}
