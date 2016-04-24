<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Database;


use Aura\Sql\ExtendedPdoInterface;

class DatabaseCache
{

	/**
	 * Database cache.
	 *
	 * @var array
	 */
	private $_cache = array();

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	private $_database;

	/**
	 * Creates cache instance.
	 *
	 * @param ExtendedPdoInterface $database Database.
	 */
	public function __construct(ExtendedPdoInterface $database)
	{
		$this->_database = $database;
	}

	/**
	 * Adds table to caching.
	 *
	 * @param string $table Table.
	 *
	 * @return void
	 */
	public function cacheTable($table)
	{
		$this->_cache[$table] = array();
	}

	/**
	 * Gets cached data.
	 *
	 * @param string      $table     Table.
	 * @param mixed       $cache_key Key.
	 * @param string|null $sql       Fallback sql used to populate cache on the fly.
	 * @param array       $values    Fallback values used together with above sql.
	 *
	 * @return array|boolean
	 */
	public function getFromCache($table, $cache_key, $sql = null, array $values = array())
	{
		if ( isset($this->_cache[$table][$cache_key]) ) {
			return $this->_cache[$table][$cache_key];
		}

		if ( isset($sql) ) {
			$result = $this->_database->fetchOne($sql, $values);

			if ( $result !== false ) {
				$this->setIntoCache($table, $cache_key, $result);

				return $this->_cache[$table][$cache_key];
			}
		}

		return false;
	}

	/**
	 * Gets cached data.
	 *
	 * @param string $table     Table.
	 * @param mixed  $cache_key Key.
	 * @param array  $data      Data.
	 *
	 * @return void
	 */
	public function setIntoCache($table, $cache_key, array $data)
	{
		if ( isset($this->_cache[$table][$cache_key]) ) {
			$this->_cache[$table][$cache_key] = array_replace($this->_cache[$table][$cache_key], $data);
		}
		else {
			$this->_cache[$table][$cache_key] = $data;
		}
	}

	/**
	 * Clears cache.
	 *
	 * @return void
	 */
	public function clear()
	{
		$tables = array_keys($this->_cache);
		$this->_cache = array();

		foreach ( $tables as $table_name ) {
			$this->cacheTable($table_name);
		}
	}

}
