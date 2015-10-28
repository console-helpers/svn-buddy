<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace aik099\SVNBuddy\Cache;


/**
 * Caches information about repository.
 */
class FileCacheStorage implements ICacheStorage
{

	/**
	 * Cache file.
	 *
	 * @var string
	 */
	private $_file;

	/**
	 * Creates instance of revision log cache.
	 *
	 * @param string $file Cache file.
	 */
	public function __construct($file)
	{
		$this->_file = $file;
	}

	/**
	 * Gets information from cache.
	 *
	 * @return array|null
	 */
	public function get()
	{
		if ( !file_exists($this->_file) ) {
			return null;
		}

		$file_contents = file_get_contents($this->_file);

		if ( strpos($this->_file, '/log_') !== false ) {
			$file_contents = gzuncompress($file_contents);
		}

		$cache = json_decode($file_contents, true);

		if ( $cache ) {
			return $cache;
		}

		return null;
	}

	/**
	 * Stores information in cache.
	 *
	 * @param array $cache Cache.
	 *
	 * @return void
	 */
	public function set(array $cache)
	{
		$file_contents = json_encode($cache);

		if ( strpos($this->_file, '/log_') !== false ) {
			$file_contents = gzcompress($file_contents);
		}

		file_put_contents($this->_file, $file_contents);
	}

	/**
	 * Invalidates cache.
	 *
	 * @return void
	 */
	public function invalidate()
	{
		if ( file_exists($this->_file) ) {
			unlink($this->_file);
		}
	}

}
