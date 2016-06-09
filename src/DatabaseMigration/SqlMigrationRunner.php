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


class SqlMigrationRunner extends AbstractMigrationRunner
{

	/**
	 * Returns supported file extension.
	 *
	 * @return string
	 */
	public function getFileExtension()
	{
		return 'sql';
	}

	/**
	 * Runs the migration.
	 *
	 * @param string           $migration_file Migration file.
	 * @param MigrationContext $context        Migration context.
	 *
	 * @return void
	 * @throws \LogicException When an empty migration is discovered.
	 */
	public function run($migration_file, MigrationContext $context)
	{
		$sqls = file_get_contents($migration_file);
		$sqls = array_filter(preg_split('/;\s+/', $sqls));

		if ( !$sqls ) {
			throw new \LogicException('The "' . basename($migration_file) . '" migration contains no SQL statements.');
		}

		$db = $context->getDatabase();

		foreach ( $sqls as $sql ) {
			$db->perform($sql);
		}
	}

}
