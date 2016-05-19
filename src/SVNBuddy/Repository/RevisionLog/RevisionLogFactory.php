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


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\BugsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\MergesPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\PathsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\ProjectsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RefsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\SummaryPlugin;

class RevisionLogFactory
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Database manager.
	 *
	 * @var DatabaseManager
	 */
	private $_databaseManager;

	/**
	 * Log message parser factory
	 *
	 * @var LogMessageParserFactory
	 */
	private $_logMessageParserFactory;

	/**
	 * Create revision log.
	 *
	 * @param Connector               $repository_connector       Repository connector.
	 * @param DatabaseManager         $database_manager           Database manager.
	 * @param LogMessageParserFactory $log_message_parser_factory Log message parser factory.
	 */
	public function __construct(
		Connector $repository_connector,
		DatabaseManager $database_manager,
		LogMessageParserFactory $log_message_parser_factory
	) {
		$this->_repositoryConnector = $repository_connector;
		$this->_databaseManager = $database_manager;
		$this->_logMessageParserFactory = $log_message_parser_factory;
	}

	/**
	 * Returns revision log for url.
	 *
	 * @param string    $repository_url Repository url.
	 * @param ConsoleIO $io             Console IO.
	 *
	 * @return RevisionLog
	 */
	public function getRevisionLog($repository_url, ConsoleIO $io = null)
	{
		// Gets database for given repository url.
		$root_url = $this->_repositoryConnector->getRootUrl($repository_url);
		$database = $this->_databaseManager->getDatabase($root_url, $io);

		// Create dependencies.
		$database_cache = new DatabaseCache($database);
		$repository_filler = new RepositoryFiller($database, $database_cache);

		// Create blank revision log.
		$revision_log = new RevisionLog($repository_url, $this->_repositoryConnector, $io);

		// Add plugins to revision log.
		$revision_log->registerPlugin(new SummaryPlugin($database, $repository_filler));
		$revision_log->registerPlugin(new PathsPlugin(
			$database,
			$repository_filler,
			$database_cache,
			$this->_repositoryConnector,
			new PathCollisionDetector()
		));
		$revision_log->registerPlugin(new ProjectsPlugin($database, $repository_filler));
		$revision_log->registerPlugin(new BugsPlugin(
			$database,
			$repository_filler,
			$root_url,
			$this->_repositoryConnector,
			$this->_logMessageParserFactory
		));
		$revision_log->registerPlugin(new MergesPlugin($database, $repository_filler));
		$revision_log->registerPlugin(new RefsPlugin($database, $repository_filler));

		// Run migrations (includes initial schema creation).
		$context = new MigrationContext($database, clone $revision_log);
		$this->_databaseManager->runMigrations($context);

		$profiler = $database->getProfiler();

		if ( $profiler instanceof StatementProfiler ) {
			$profiler->trackDuplicates(true);
		}

		$revision_log->refresh(false);

		if ( $profiler instanceof StatementProfiler ) {
			$profiler->trackDuplicates(false);
		}

		return $revision_log;
	}

}
