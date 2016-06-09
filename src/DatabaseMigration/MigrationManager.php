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
	 * @var \ArrayAccess
	 */
	private $_container;

	/**
	 * Migration manager context.
	 *
	 * @var MigrationContext
	 */
	private $_context;

	/**
	 * Migration runners.
	 *
	 * @var AbstractMigrationRunner[]
	 */
	private $_migrationRunners = array();

	/**
	 * Creates migration manager instance.
	 *
	 * @param string       $migrations_directory Migrations directory.
	 * @param \ArrayAccess $container            Container.
	 *
	 * @throws \InvalidArgumentException When migrations directory does not exist.
	 */
	public function __construct($migrations_directory, \ArrayAccess $container)
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
	 * Registers a migration runner.
	 *
	 * @param AbstractMigrationRunner $migration_runner Migration runner.
	 *
	 * @return void
	 */
	public function registerMigrationRunner(AbstractMigrationRunner $migration_runner)
	{
		$this->_migrationRunners[$migration_runner->getFileExtension()] = $migration_runner;
	}

	/**
	 * Creates new migration.
	 *
	 * @param string $name           Migration name.
	 * @param string $file_extension Migration file extension.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When migration name/file extension is invalid.
	 * @throws \LogicException When new migration already exists.
	 */
	public function createMigration($name, $file_extension)
	{
		if ( preg_replace('/[a-z\d\._]/', '', $name) ) {
			throw new \InvalidArgumentException(
				'The migration name can consist only from alpha-numeric characters, as well as dots and underscores.'
			);
		}

		if ( !in_array($file_extension, $this->getMigrationFileExtensions()) ) {
			throw new \InvalidArgumentException(
				'The migration runner for "' . $file_extension . '" file extension is not registered.'
			);
		}

		$migration_file = $this->_migrationsDirectory . '/' . date('Ymd_Hi') . '_' . $name . '.' . $file_extension;

		if ( file_exists($migration_file) ) {
			throw new \LogicException('The migration file "' . basename($migration_file) . '" already exists.');
		}

		file_put_contents($migration_file, $this->_migrationRunners[$file_extension]->getTemplate());

		return basename($migration_file);
	}

	/**
	 * Returns supported migration file extensions.
	 *
	 * @return array
	 * @throws \LogicException When no migration runners added.
	 */
	public function getMigrationFileExtensions()
	{
		if ( !$this->_migrationRunners ) {
			throw new \LogicException('No migrations runners registered.');
		}

		return array_keys($this->_migrationRunners);
	}

	/**
	 * Executes outstanding migrations.
	 *
	 * @param MigrationContext $context Context.
	 *
	 * @return void
	 */
	public function run(MigrationContext $context)
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
	 * @param MigrationContext $context Context.
	 *
	 * @return void
	 */
	protected function setContext(MigrationContext $context)
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
		$migrations = array();
		$file_extensions = $this->getMigrationFileExtensions();

		// Use "DirectoryIterator" instead of "glob", because it works within PHAR files as well.
		$directory_iterator = new \DirectoryIterator($this->_migrationsDirectory);

		foreach ( $directory_iterator as $file ) {
			if ( $file->isFile() && in_array($file->getExtension(), $file_extensions) ) {
				$migrations[] = $file->getBasename();
			}
		}

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

			$this->_migrationRunners[$migration_type]->run(
				$this->_migrationsDirectory . '/' . $migration,
				$this->_context
			);

			$sql = 'INSERT INTO Migrations (Name, ExecutedOn)
					VALUES (:name, :executed_on)';
			$db->perform($sql, array('name' => $migration, 'executed_on' => time()));
			$db->commit();
		}
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
