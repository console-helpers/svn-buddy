<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Cache;


/**
 * Caches information about repository.
 */
class FileCacheStorage implements ICacheStorage
{

	const COMPRESS_THRESHOLD = 10240;

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

		$parent_path = dirname($this->_file);

		if ( !file_exists($parent_path) ) {
			mkdir($parent_path, 0777, true);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getUniqueId()
	{
		return $this->_file;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \RuntimeException When cache file doesn't exist.
	 */
	public function getSize()
	{
		if ( !file_exists($this->_file) ) {
			throw new \RuntimeException('File "' . $this->_file . '" does not exist.');
		}

		return filesize($this->_file);
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
		$first_symbol = substr($file_contents, 0, 1);

		if ( !in_array($first_symbol, array('{', '[')) ) {
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

		if ( strlen($file_contents) > self::COMPRESS_THRESHOLD ) {
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
