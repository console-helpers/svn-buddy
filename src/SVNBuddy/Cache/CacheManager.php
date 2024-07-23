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
use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;

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
	 * Size helper.
	 *
	 * @var SizeHelper
	 */
	private $_sizeHelper;

	/**
	 * Statistics.
	 *
	 * @var array
	 */
	private $_statistics = array();

	/**
	 * Create cache manager.
	 *
	 * @param string     $working_directory Working directory.
	 * @param SizeHelper $size_helper       Size helper.
	 * @param ConsoleIO  $io                Console IO.
	 */
	public function __construct($working_directory, SizeHelper $size_helper, ConsoleIO $io = null)
	{
		$this->_workingDirectory = $working_directory;
		$this->_sizeHelper = $size_helper;
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
		$duration = $this->durationIntoSeconds($duration);

		$storage = $this->_getStorage($name, $duration);
		$storage->set(array(
			'name' => $name,
			'invalidator' => $invalidator,
			'duration' => $duration,
			'expiration' => $duration ? time() + $duration : null,
			'data' => $value,
		));
	}

	/**
	 * Gets value from cache.
	 *
	 * @param string  $name        Name.
	 * @param mixed   $invalidator Invalidator.
	 * @param integer $duration    Duration in seconds.
	 *
	 * @return mixed
	 */
	public function getCache($name, $invalidator = null, $duration = null)
	{
		$storage = $this->_getStorage($name, $this->durationIntoSeconds($duration));
		$cache = $storage->get();

		if ( !is_array($cache) || $cache['invalidator'] !== $invalidator ) {
			$storage->invalidate();
			$this->registerMiss($storage, is_array($cache) ? 'invalidated' : 'absent');

			return null;
		}

		if ( $cache['expiration'] && $cache['expiration'] < time() ) {
			$storage->invalidate();
			$this->registerMiss($storage, 'expired');

			return null;
		}

		$this->registerHit($storage);

		return $cache['data'];
	}

	/**
	 * Registers hit.
	 *
	 * @param ICacheStorage $storage Storage.
	 *
	 * @return void
	 */
	protected function registerHit(ICacheStorage $storage)
	{
		if ( !isset($this->_io) || !$this->_io->isVerbose() ) {
			return;
		}

		$unique_id = $storage->getUniqueId();
		$message = $unique_id;

		$this->initStatistics($storage);

		$this->_statistics[$unique_id]['hits']++;
		$message .= sprintf(
			' (hit #%d: %s)',
			$this->_statistics[$unique_id]['hits'],
			$this->_sizeHelper->formatSize($storage->getSize())
		);

		$this->_io->writeln(array(
			'',
			'<debug>[cache]: ' . $message . '</debug>',
		));
	}

	/**
	 * Registers miss.
	 *
	 * @param ICacheStorage $storage Storage.
	 * @param string        $reason  Reason.
	 *
	 * @return void
	 */
	protected function registerMiss(ICacheStorage $storage, $reason)
	{
		if ( !isset($this->_io) || !$this->_io->isVerbose() ) {
			return;
		}

		$unique_id = $storage->getUniqueId();
		$message = $unique_id;

		$this->initStatistics($storage);

		$this->_statistics[$unique_id]['misses']++;
		$message .= sprintf(
			' (miss:' . $reason . ' #%d)',
			$this->_statistics[$unique_id]['misses']
		);

		$this->_io->writeln(array(
			'',
			'<debug>[cache]: ' . $message . '</debug>',
		));
	}

	/**
	 * Initializes statistics.
	 *
	 * @param ICacheStorage $storage Storage.
	 *
	 * @return void
	 */
	protected function initStatistics(ICacheStorage $storage)
	{
		$unique_id = $storage->getUniqueId();

		if ( !array_key_exists($unique_id, $this->_statistics) ) {
			$this->_statistics[$unique_id] = array('hits' => 0, 'misses' => 0);
		}
	}

	/**
	 * Deletes value from cache.
	 *
	 * @param string  $name     Name.
	 * @param integer $duration Duration in seconds.
	 *
	 * @return void
	 */
	public function deleteCache($name, $duration = null)
	{
		$storage = $this->_getStorage($name, $this->durationIntoSeconds($duration));
		$storage->invalidate();
	}

	/**
	 * Converts duration into seconds.
	 *
	 * @param integer $duration Duration in seconds.
	 *
	 * @return integer|null
	 */
	protected function durationIntoSeconds($duration = null)
	{
		if ( !isset($duration) ) {
			return null;
		}

		if ( is_numeric($duration) ) {
			$duration .= ' seconds';
		}

		$now = time();

		return strtotime('+' . $duration, $now) - $now;
	}

	/**
	 * Returns file-based cache storage.
	 *
	 * @param string  $name     Cache name.
	 * @param integer $duration Duration in seconds.
	 *
	 * @return ICacheStorage
	 * @throws \InvalidArgumentException When namespace is missing in the name.
	 */
	private function _getStorage($name, $duration = null)
	{
		$name_parts = explode(':', $name, 2);

		if ( count($name_parts) != 2 ) {
			throw new \InvalidArgumentException('The $name parameter must be in "namespace:name" format.');
		}

		$filename_parts = array(
			$name_parts[0],
			substr(hash_hmac('sha1', $name_parts[1], 'svn-buddy'), 0, 8),
			'D' . (isset($duration) ? $duration : 'INF'),
		);

		return new FileCacheStorage(
			$this->_workingDirectory . DIRECTORY_SEPARATOR . implode('_', $filename_parts) . '.cache'
		);
	}

}
