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


interface ICacheStorage
{

	/**
	 * Returns unique id of the cache storage.
	 *
	 * @return string
	 */
	public function getUniqueId();

	/**
	 * Returns cache size.
	 *
	 * @return integer
	 */
	public function getSize();

	/**
	 * Gets information from cache.
	 *
	 * @return array|null
	 */
	public function get();

	/**
	 * Stores information in cache.
	 *
	 * @param array $cache Cache.
	 *
	 * @return void
	 */
	public function set(array $cache);

	/**
	 * Invalidates cache.
	 *
	 * @return void
	 */
	public function invalidate();

}
