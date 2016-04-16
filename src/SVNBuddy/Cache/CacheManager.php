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


use ConsoleHelpers\ConsoleKit\ConsoleIO;

class CacheManager
{

	/**
	 * Working directory.
	 *
	 * @var string
	 */
	private $_workingDirectory;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Create cache manager.
	 *
	 * @param string    $working_directory Working directory.
	 * @param ConsoleIO $io                Console IO.
	 */
	public function __construct($working_directory, ConsoleIO $io = null)
	{
		$this->_workingDirectory = $working_directory;
		$this->_io = $io;
	}

	/**
	 * Sets value in cache.
	 *
	 * @param string  $name        Name.
	 * @param mixed   $value       Value.
	 * @param mixed   $invalidator Invalidator.
	 * @param integer $duration    Duration in seconds.
	 *
	 * @return void
	 */
	public function setCache($name, $value, $invalidator = null, $duration = null)
	{
		if ( is_numeric($duration) ) {
			$duration .= ' seconds';
		}

		$storage = $this->_getStorage($name);
		$storage->set(array(
			'name' => $name,
			'invalidator' => $invalidator,
			'expiration' => $duration ? strtotime('+' . $duration) : null,
			'data' => $value,
		));
	}

	/**
	 * Gets value from cache.
	 *
	 * @param string $name        Name.
	 * @param mixed  $invalidator Invalidator.
	 *
	 * @return mixed
	 */
	public function getCache($name, $invalidator = null)
	{
		$storage = $this->_getStorage($name);
		$cache = $storage->get();

		if ( !is_array($cache) || $cache['invalidator'] !== $invalidator ) {
			$storage->invalidate();

			return null;
		}

		if ( $cache['expiration'] && $cache['expiration'] < time() ) {
			$storage->invalidate();

			return null;
		}

		return $cache['data'];
	}

	/**
	 * Returns file-based cache storage.
	 *
	 * @param string $name Cache name.
	 *
	 * @return ICacheStorage
	 * @throws \InvalidArgumentException When namespace is missing in the name.
	 */
	private function _getStorage($name)
	{
		$parts = explode(':', $name, 2);

		if ( count($parts) != 2 ) {
			throw new \InvalidArgumentException('The $name parameter must be in "namespace:name" format.');
		}

		$name_hash = substr(hash_hmac('sha1', $parts[1], 'svn-buddy'), 0, 8);
		$cache_filename = $this->_workingDirectory . DIRECTORY_SEPARATOR . $parts[0] . '_' . $name_hash . '.cache';

		if ( isset($this->_io) && $this->_io->isVerbose() ) {
			$this->_io->writeln(array(
				'',
				'<fg=white;bg=magenta>[cache]: ' . $cache_filename . '</>',
			));
		}

		return new FileCacheStorage($cache_filename);
	}

}
