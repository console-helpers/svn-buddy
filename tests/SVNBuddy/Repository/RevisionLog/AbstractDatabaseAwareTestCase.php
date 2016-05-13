<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Container;
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;
use Tests\ConsoleHelpers\SVNBuddy\Database\AbstractDatabaseAwareTestCase as BaseAbstractDatabaseAwareTestCase;

abstract class AbstractDatabaseAwareTestCase extends BaseAbstractDatabaseAwareTestCase
{

	/**
	 * Creates database for testing with correct db structure.
	 *
	 * @return ExtendedPdoInterface
	 */
	protected function createDatabase()
	{
		$db = parent::createDatabase();

		$container = new Container();

		$migration_manager = $container['migration_manager'];
		$migration_manager->run(new MigrationContext($db));

		return $db;
	}

}
