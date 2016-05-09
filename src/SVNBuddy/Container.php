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
use ConsoleHelpers\SVNBuddy\Database\MigrationManager;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\InPortalMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
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
		);

		$this->extend('output', function ($output, $c) {
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

			$statement_profiler->setActive(true);

			return $statement_profiler;
		};

		$this['migration_manager'] = function ($c) {
			$migrations_directory = dirname(dirname(__DIR__)) . '/migrations';

			return new MigrationManager($migrations_directory, $c);
		};

		$this['revision_log_factory'] = function ($c) {
			return new RevisionLogFactory($c['repository_connector'], $c['cache_manager']);
		};

		$this['log_message_parser_factory'] = function () {
			return new LogMessageParserFactory();
		};

		$this['revision_list_parser'] = function () {
			return new RevisionListParser();
		};

		$this['repository_connector'] = function ($c) {
			return new Connector($c['config_editor'], $c['process_factory'], $c['io'], $c['cache_manager']);
		};

		$this['date_helper'] = function () {
			return new DateHelper();
		};

		$this['size_helper'] = function () {
			return new SizeHelper();
		};

		$this['editor'] = function () {
			return new InteractiveEditor();
		};
	}

}
