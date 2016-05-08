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
use Pimple\Container;

class MigrationManagerContext
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
	 * @var Container
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
	 * @param Container $container Container.
	 *
	 * @return void
	 */
	public function setContainer(Container $container)
	{
		$this->_container = $container;
	}

	/**
	 * Returns container.
	 *
	 * @return Container
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
