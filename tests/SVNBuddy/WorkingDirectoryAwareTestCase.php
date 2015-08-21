<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy;


use aik099\SVNBuddy\WorkingDirectory;
use Mockery as m;

abstract class WorkingDirectoryAwareTestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * Original value of "HOME" environment variable.
	 *
	 * @var string
	 */
	private $_homeDirectoryBackup;

	protected function setUp()
	{
		parent::setUp();

		$this->_homeDirectoryBackup = getenv('HOME');

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
		$working_directory = new WorkingDirectory();

		return $working_directory->get();
	}

	protected function tearDown()
	{
		parent::tearDown();

		$this->_restoreHomeDirectory($this->_homeDirectoryBackup);
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
	 * @param string $original_home_directory Directory.
	 */
	private function _restoreHomeDirectory($original_home_directory)
	{
		$current_home_directory = getenv('HOME');

		if ( $current_home_directory && $current_home_directory != $original_home_directory ) {
			shell_exec('rm -Rf ' . escapeshellarg($current_home_directory));
			putenv('HOME=' . $original_home_directory);
		}
	}

}
