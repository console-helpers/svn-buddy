<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy;


use ConsoleHelpers\DatabaseMigration\MigrationManager;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Config\CommandConfig;
use ConsoleHelpers\SVNBuddy\Container;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\Helper\OutputHelper;
use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;
use ConsoleHelpers\SVNBuddy\InteractiveEditor;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\CommitMessageBuilder;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\MergeTemplateFactory;
use ConsoleHelpers\SVNBuddy\Repository\Connector\CommandFactory;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionPrinter;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;
use ConsoleHelpers\SVNBuddy\Updater\Updater;
use Tests\ConsoleHelpers\ConsoleKit\ContainerTest as BaseContainerTest;

class ContainerTest extends BaseContainerTest
{

	public static function instanceDataProvider()
	{
		$instance_data = parent::instanceDataProvider();

		$new_instance_data = array(
			'app_name' => array('SVN-Buddy', 'app_name'),
			'app_version' => array('@git-version@', 'app_version'),
			'config_defaults' => array(
				array(
					'repository-connector.username' => '',
					'repository-connector.password' => '',
					'repository-connector.last-revision-cache-duration' => '10 minutes',
					'update-channel' => 'stable',
					'theme' => 'dark',
				),
				'config_defaults',
			),
			'working_directory_sub_folder' => array('.svn-buddy', 'working_directory_sub_folder'),
			'process_factory' => array(ProcessFactory::class, 'process_factory'),
			'merge_source_detector' => array(MergeSourceDetectorAggregator::class, 'merge_source_detector'),
			'repository_url_resolver' => array(UrlResolver::class, 'repository_url_resolver'),
			'cache_manager' => array(CacheManager::class, 'cache_manager'),
			'statement_profiler' => array(StatementProfiler::class, 'statement_profiler'),
			'migration_manager' => array(MigrationManager::class, 'migration_manager'),
			'revision_log_factory' => array(RevisionLogFactory::class, 'revision_log_factory'),
			'log_message_parser_factory' => array(LogMessageParserFactory::class, 'log_message_parser_factory'),
			'revision_list_parser' => array(RevisionListParser::class, 'revision_list_parser'),
			'revision_printer' => array(RevisionPrinter::class, 'revision_printer'),
			'command_factory' => array(CommandFactory::class, 'command_factory'),
			'repository_connector' => array(Connector::class, 'repository_connector'),
			'commit_message_builder' => array(CommitMessageBuilder::class, 'commit_message_builder'),
			'working_copy_resolver' => array(WorkingCopyResolver::class, 'working_copy_resolver'),
			'working_copy_conflict_tracker' => array(WorkingCopyConflictTracker::class, 'working_copy_conflict_tracker'),
			'merge_template_factory' => array(MergeTemplateFactory::class, 'merge_template_factory'),
			'command_config' => array(CommandConfig::class, 'command_config'),
			'date_helper' => array(DateHelper::class, 'date_helper'),
			'size_helper' => array(SizeHelper::class, 'size_helper'),
			'output_helper' => array(OutputHelper::class, 'output_helper'),
			'editor' => array(InteractiveEditor::class, 'editor'),
		);

		// The "updater" service requires executable to be writable (assuming it's PHAR file all the time).
		$local_phar_file = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];

		if ( is_writable($local_phar_file) ) {
			$new_instance_data['updater'] = array(Updater::class, 'updater');
		}

		return array_merge($instance_data, $new_instance_data);
	}

	/**
	 * Creates container instance.
	 *
	 * @return Container
	 */
	protected function createContainer()
	{
		return new Container();
	}

}
