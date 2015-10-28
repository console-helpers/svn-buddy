<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy;


use ConsoleHelpers\SVNBuddy\Exception\ApplicationException;

class WorkingDirectory
{

	/**
	 * Name of sub folder placed in user's home directory.
	 *
	 * @var string
	 */
	private $_subFolder;

	/**
	 * WorkingDirectory constructor.
	 *
	 * @param string $sub_folder Sub folder.
	 *
	 * @throws ApplicationException When $sub_folder is a path or empty.
	 */
	public function __construct($sub_folder)
	{
		if ( !strlen($sub_folder) || strpos($sub_folder, DIRECTORY_SEPARATOR) !== false ) {
			throw new ApplicationException('The $sub_folder is a path or empty.');
		}

		$this->_subFolder = $sub_folder;
	}

	/**
	 * Creates (if missing) working directory and returns full path to it.
	 *
	 * @return string
	 */
	public function get()
	{
		$working_directory = $this->getUserHomeDirectory() . '/' . $this->_subFolder;

		if ( !file_exists($working_directory) ) {
			mkdir($working_directory, 0777, true);
		}

		return $working_directory;
	}

	/**
	 * Returns path to user's home directory.
	 *
	 * @return string
	 * @throws ApplicationException When user's home directory can't be found.
	 */
	protected function getUserHomeDirectory()
	{
		if ( defined('PHP_WINDOWS_VERSION_MAJOR') ) {
			if ( !getenv('APPDATA') ) {
				throw new ApplicationException('The APPDATA environment variable must be set to run correctly.');
			}

			return strtr(getenv('APPDATA'), '\\', '/');
		}

		if ( !getenv('HOME') ) {
			throw new ApplicationException('The HOME environment variable must be set to run correctly.');
		}

		return rtrim(getenv('HOME'), '/');
	}

}
