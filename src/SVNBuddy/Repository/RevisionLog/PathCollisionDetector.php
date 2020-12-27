<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


class PathCollisionDetector
{

	/**
	 * Known paths.
	 *
	 * @var array
	 */
	private $_knownPaths = array();

	/**
	 * Expanded array of paths.
	 *
	 * @var array
	 */
	private $_expandedPaths = array('/' => 1);

	/**
	 * Longest path length.
	 *
	 * @var integer
	 */
	private $_longestPathLength = 1;

	/**
	 * Adds paths.
	 *
	 * @param array $paths Paths.
	 *
	 * @return void
	 */
	public function addPaths(array $paths)
	{
		foreach ( $paths as $path ) {
			$parent_path = rtrim($path, '/');
			$this->_knownPaths[$path] = true;

			do {
				$this->_expandedPaths[$parent_path . '/'] = strlen($parent_path . '/');
				$parent_path = dirname($parent_path);
			} while ( $parent_path !== '/' && $parent_path !== '' );
		}

		$path_count = count($this->_expandedPaths);

		if ( $path_count > 1 ) {
			$this->_longestPathLength = call_user_func_array('max', \array_values($this->_expandedPaths));
		}
		elseif ( $path_count === 1 ) {
			$this->_longestPathLength = current($this->_expandedPaths);
		}
		else {
			$this->_longestPathLength = 0; // @codeCoverageIgnore
		}
	}

	/**
	 * Checks path collision.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	public function isCollision($path)
	{
		if ( isset($this->_knownPaths[$path]) || !$this->_knownPaths ) {
			return false;
		}

		$path = substr($path, 0, $this->_longestPathLength);

		return isset($this->_expandedPaths[$path]);
	}

}
