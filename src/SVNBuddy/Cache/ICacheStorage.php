<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Cache;


interface ICacheStorage
{

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
