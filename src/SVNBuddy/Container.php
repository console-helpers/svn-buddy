<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy;


use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\DatabaseMigration\MigrationManager;
use ConsoleHelpers\DatabaseMigration\PhpMigrationRunner;
use ConsoleHelpers\DatabaseMigration\SqlMigrationRunner;
use ConsoleHelpers\SVNBuddy\Config\CommandConfig;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\Helper\OutputHelper;
use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\InPortalMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessageBuilder;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\DatabaseManager;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionPrinter;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class Container extends \ConsoleHelpers\ConsoleKit\Container
{

	/**
	 * {@inheritdoc}
	 */
	public function __construct(array $values = array())
	{
		parent::__construct($values);

		$this['app_name'] = 'SVN-Buddy';
		$this['app_version'] = '@git-version@';

		$this['working_directory_sub_folder'] = '.svn-buddy';

		$this['config_defaults'] = array(
			'repository-connector.username' => '',
			'repository-connector.password' => '',
			'repository-connector.last-revision-cache-duration' => '10 minutes',
			'update-channel' => 'stable',
		);

		$this->extend('output', function ($output) {
			/** @var OutputInterface $output */
			$output->getFormatter()->setStyle('debug', new OutputFormatterStyle('white', 'magenta'));

			return $output;
		});

		$this['process_factory'] = function () {
			return new ProcessFactory();
		};

		$this['merge_source_detector'] = function () {
			$merge_source_detector = new MergeSourceDetectorAggregator(0);
			$merge_source_detector->add(new ClassicMergeSourceDetector(0));
			$merge_source_detector->add(new InPortalMergeSourceDetector(50));

			return $merge_source_detector;
		};

		$this['repository_url_resolver'] = function ($c) {
			return new UrlResolver($c['repository_connector']);
		};

		$this['cache_manager'] = function ($c) {
			return new CacheManager($c['working_directory'], $c['size_helper'], $c['io']);
		};

		$this['statement_profiler'] = function () {
			$statement_profiler = new StatementProfiler();

			// The "AbstractPlugin::getLastRevision" method.
			$statement_profiler->ignoreDuplicateStatement('SELECT LastRevision FROM PluginData WHERE Name = :name');

			// The "AbstractPlugin::getProject" method.
			$statement_profiler->ignoreDuplicateStatement('SELECT Id FROM Projects WHERE Path = :path');

			// The "AbstractDatabaseCollectorPlugin::getProjects" method.
			$statement_profiler->ignoreDuplicateStatement(
				'SELECT Path, Id AS PathId, RevisionAdded, RevisionDeleted, RevisionLastSeen
				FROM Paths
				WHERE PathHash IN (:path_hashes)'
			);

			// The "ProjectsPlugin::createRepositoryWideProject" method.
			$statement_profiler->ignoreDuplicateStatement(
				'SELECT Id FROM Paths WHERE ProjectPath = :project_path LIMIT 100'
			);

			$statement_profiler->setActive(true);
			$statement_profiler->trackDuplicates(false);

			return $statement_profiler;
		};

		$this['project_root_folder'] = function () {
			return dirname(dirname(__DIR__));
		};

		$this['migration_manager'] = function ($c) {
			$migrations_directory = $c['project_root_folder'] . '/migrations';
			$migration_manager = new MigrationManager($migrations_directory, $c);
			$migration_manager->registerMigrationRunner(new SqlMigrationRunner());
			$migration_manager->registerMigrationRunner(new PhpMigrationRunner());

			return $migration_manager;
		};

		$this['db_manager'] = function ($c) {
			return new DatabaseManager($c['working_directory'], $c['migration_manager'], $c['statement_profiler']);
		};

		$this['revision_log_factory'] = function ($c) {
			return new RevisionLogFactory(
				$c['repository_connector'],
				$c['db_manager'],
				$c['log_message_parser_factory']
			);
		};

		$this['log_message_parser_factory'] = function () {
			return new LogMessageParserFactory();
		};

		$this['revision_list_parser'] = function () {
			return new RevisionListParser();
		};

		$this['revision_printer'] = function ($c) {
			return new RevisionPrinter($c['date_helper'], $c['output_helper']);
		};

		$this['repository_connector'] = function ($c) {
			return new Connector($c['config_editor'], $c['process_factory'], $c['io'], $c['cache_manager']);
		};

		$this['commit_message_builder'] = function ($c) {
			return new CommitMessageBuilder(
				$c['repository_connector'],
				$c['revision_list_parser'],
				$c['revision_log_factory'],
				$c['working_copy_conflict_tracker']
			);
		};

		$this['working_copy_resolver'] = function ($c) {
			return new WorkingCopyResolver($c['repository_connector']);
		};

		$this['working_copy_conflict_tracker'] = function ($c) {
			return new WorkingCopyConflictTracker($c['repository_connector'], $c['command_config']);
		};

		$this['command_config'] = function ($c) {
			return new CommandConfig($c['config_editor'], $c['working_copy_resolver']);
		};

		$this['date_helper'] = function () {
			return new DateHelper();
		};

		$this['size_helper'] = function () {
			return new SizeHelper();
		};

		$this['output_helper'] = function () {
			return new OutputHelper();
		};

		$this['editor'] = function () {
			return new InteractiveEditor();
		};
	}

}
