<?php
/**
 * This file is part of the DB-Migration library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/db-migration
 */

namespace ConsoleHelpers\DatabaseMigration;


use Aura\Sql\ExtendedPdoInterface;

class MigrationContext
{

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	private $_database;

	/**
	 * Container.
	 *
	 * @var \ArrayAccess
	 */
	private $_container;

	/**
	 * Creates migration manager context.
	 *
	 * @param ExtendedPdoInterface $database Database.
	 */
	public function __construct(ExtendedPdoInterface $database)
	{
		$this->_database = $database;
	}

	/**
	 * Sets container.
	 *
	 * @param \ArrayAccess $container Container.
	 *
	 * @return void
	 */
	public function setContainer(\ArrayAccess $container)
	{
		$this->_container = $container;
	}

	/**
	 * Returns container.
	 *
	 * @return \ArrayAccess
	 */
	public function getContainer()
	{
		return $this->_container;
	}

	/**
	 * Returns database.
	 *
	 * @return ExtendedPdoInterface
	 */
	public function getDatabase()
	{
		return $this->_database;
	}

}
