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


use Pimple\Container;

class MigrationManager
{

	/**
	 * Migrations directory.
	 *
	 * @var string
	 */
	private $_migrationsDirectory;

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private $_container;

	/**
	 * Migration manager context.
	 *
	 * @var MigrationManagerContext
	 */
	private $_context;

	/**
	 * Creates migration manager instance.
	 *
	 * @param string    $migrations_directory Migrations directory.
	 * @param Container $container            Container.
	 *
	 * @throws \InvalidArgumentException When migrations directory does not exist.
	 */
	public function __construct($migrations_directory, Container $container)
	{
		if ( !file_exists($migrations_directory) || !is_dir($migrations_directory) ) {
			throw new \InvalidArgumentException(
				'The "' . $migrations_directory . '" does not exist or not a directory.'
			);
		}

		$this->_migrationsDirectory = $migrations_directory;
		$this->_container = $container;
	}

	/**
	 * Executes outstanding migrations.
	 *
	 * @param MigrationManagerContext $context Context.
	 *
	 * @return void
	 */
	public function run(MigrationManagerContext $context)
	{
		$this->setContext($context);
		$this->createMigrationsTable();

		$all_migrations = $this->getAllMigrations();
		$executed_migrations = $this->getExecutedMigrations();

		$migrations_to_execute = array_diff($all_migrations, $executed_migrations);
		$this->executeMigrations($migrations_to_execute);

		$migrations_to_delete = array_diff($executed_migrations, $all_migrations);
		$this->deleteMigrations($migrations_to_delete);
	}

	/**
	 * Sets current context.
	 *
	 * @param MigrationManagerContext $context Context.
	 *
	 * @return void
	 */
	protected function setContext(MigrationManagerContext $context)
	{
		$this->_context = $context;
		$this->_context->setContainer($this->_container);
	}

	/**
	 * Creates migration table, when missing.
	 *
	 * @return void
	 */
	protected function createMigrationsTable()
	{
		$db = $this->_context->getDatabase();

		$sql = "SELECT name
				FROM sqlite_master
				WHERE type = 'table' AND name = :table_name";
		$migrations_table = $db->fetchValue($sql, array('table_name' => 'Migrations'));

		if ( $migrations_table !== false ) {
			return;
		}

		$sql = 'CREATE TABLE "Migrations" (
					"Name" TEXT(255,0) NOT NULL,
					"ExecutedOn" INTEGER NOT NULL,
					PRIMARY KEY("Name")
				)';
		$db->perform($sql);
	}

	/**
	 * Returns all migrations.
	 *
	 * @return array
	 */
	protected function getAllMigrations()
	{
		$migrations = glob($this->_migrationsDirectory . '/*.{sql,php}', GLOB_BRACE | GLOB_NOSORT);
		$migrations = array_map('basename', $migrations);
		sort($migrations);

		return $migrations;
	}

	/**
	 * Returns executed migrations.
	 *
	 * @return array
	 */
	protected function getExecutedMigrations()
	{
		$sql = 'SELECT Name
				FROM Migrations';

		return $this->_context->getDatabase()->fetchCol($sql);
	}

	/**
	 * Executes migrations.
	 *
	 * @param array $migrations Migrations.
	 *
	 * @return void
	 */
	protected function executeMigrations(array $migrations)
	{
		if ( !$migrations ) {
			return;
		}

		$db = $this->_context->getDatabase();

		foreach ( $migrations as $migration ) {
			$db->beginTransaction();
			$migration_type = pathinfo($migration, PATHINFO_EXTENSION);

			if ( $migration_type === 'sql' ) {
				$this->executeSQLMigration($migration);
			}
			elseif ( $migration_type === 'php' ) {
				$this->executePHPMigration($migration);
			}

			$sql = 'INSERT INTO Migrations (Name, ExecutedOn)
					VALUES (:name, :executed_on)';
			$db->perform($sql, array('name' => $migration, 'executed_on' => time()));
			$db->commit();
		}
	}

	/**
	 * Executes SQL migration.
	 *
	 * @param string $migration Migration.
	 *
	 * @return void
	 * @throws \LogicException When an empty migration is discovered.
	 */
	protected function executeSQLMigration($migration)
	{
		$sqls = file_get_contents($this->_migrationsDirectory . '/' . $migration);
		$sqls = array_filter(preg_split('/;\s+/', $sqls));

		if ( !$sqls ) {
			throw new \LogicException('The "' . $migration . '" migration contains no SQL statements.');
		}

		$db = $this->_context->getDatabase();

		foreach ( $sqls as $sql ) {
			$db->perform($sql);
		}
	}

	/**
	 * Executes PHP migration.
	 *
	 * @param string $migration Migration.
	 *
	 * @return void
	 * @throws \LogicException When migration doesn't contain a closure.
	 */
	protected function executePHPMigration($migration)
	{
		$closure = require $this->_migrationsDirectory . '/' . $migration;

		if ( !is_callable($closure) ) {
			throw new \LogicException('The "' . $migration . '" migration doesn\'t return a closure.');
		}

		call_user_func($closure, $this->_context);
	}

	/**
	 * Deletes migrations.
	 *
	 * @param array $migrations Migrations.
	 *
	 * @return void
	 */
	protected function deleteMigrations(array $migrations)
	{
		if ( !$migrations ) {
			return;
		}

		$sql = 'DELETE FROM Migrations
				WHERE Name IN (:names)';
		$this->_context->getDatabase()->perform($sql, array('names' => $migrations));
	}

}
