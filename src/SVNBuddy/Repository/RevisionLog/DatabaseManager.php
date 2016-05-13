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


use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationManager;
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class DatabaseManager
{

	/**
	 * Working directory.
	 *
	 * @var string
	 */
	private $_workingDirectory;

	/**
	 * Migration manager.
	 *
	 * @var MigrationManager
	 */
	private $_migrationManager;

	/**
	 * Statement profiler.
	 *
	 * @var StatementProfiler
	 */
	private $_statementProfiler;

	/**
	 * Database manager constructor.
	 *
	 * @param string            $working_directory  Working directory.
	 * @param MigrationManager  $migration_manager  Migration manager.
	 * @param StatementProfiler $statement_profiler Statement profiler.
	 */
	public function __construct(
		$working_directory,
		MigrationManager $migration_manager,
		StatementProfiler $statement_profiler
	) {
		$this->_workingDirectory = $working_directory;
		$this->_migrationManager = $migration_manager;
		$this->_statementProfiler = $statement_profiler;
	}

	/**
	 * Returns db for given repository.
	 *
	 * @param string    $repository_url Repository url.
	 * @param ConsoleIO $io             Console IO.
	 *
	 * @return ExtendedPdoInterface
	 */
	public function getDatabase($repository_url, ConsoleIO $io = null)
	{
		if ( preg_match(Connector::URL_REGEXP, $repository_url, $regs) ) {
			$sub_folder = $regs[2] . $regs[3] . $regs[4];
		}
		else {
			$sub_folder = 'misc';
		}

		$parent_path = $this->_workingDirectory . '/' . $sub_folder;

		if ( !file_exists($parent_path) ) {
			mkdir($parent_path, 0777, true);
		}

		$db = new ExtendedPdo('sqlite:' . $parent_path . '/log_' . crc32($repository_url) . '.sqlite');

		$profiler = clone $this->_statementProfiler;
		$profiler->setIO($io);

		$db->setProfiler($profiler);

		return $db;
	}

	/**
	 * Runs outstanding migrations on the database.
	 *
	 * @param MigrationContext $context Context.
	 *
	 * @return void
	 */
	public function runMigrations(MigrationContext $context)
	{
		$this->_migrationManager->run($context);
	}

}
