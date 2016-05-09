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


use ConsoleHelpers\SVNBuddy\Container;
use Tests\ConsoleHelpers\ConsoleKit\ContainerTest as BaseContainerTest;

class ContainerTest extends BaseContainerTest
{

	public function instanceDataProvider()
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
				),
				'config_defaults',
			),
			'working_directory_sub_folder' => array('.svn-buddy', 'working_directory_sub_folder'),
			'process_factory' => array('ConsoleHelpers\\SVNBuddy\\Process\\ProcessFactory', 'process_factory'),
			'merge_source_detector' => array('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\MergeSourceDetectorAggregator', 'merge_source_detector'),
			'repository_url_resolver' => array('ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver', 'repository_url_resolver'),
			'cache_manager' => array('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager', 'cache_manager'),
			'statement_profiler' => array('ConsoleHelpers\SVNBuddy\Database\StatementProfiler', 'statement_profiler'),
			'migration_manager' => array('ConsoleHelpers\SVNBuddy\Database\MigrationManager', 'migration_manager'),
			'revision_log_factory' => array('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\RevisionLogFactory', 'revision_log_factory'),
			'log_message_parser_factory' => array('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory', 'log_message_parser_factory'),
			'revision_list_parser' => array('ConsoleHelpers\\SVNBuddy\\Repository\\Parser\\RevisionListParser', 'revision_list_parser'),
			'repository_connector' => array('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector', 'repository_connector'),
			'date_helper' => array('ConsoleHelpers\\SVNBuddy\\Helper\\DateHelper', 'date_helper'),
			'editor' => array('ConsoleHelpers\\SVNBuddy\\InteractiveEditor', 'editor'),
		);

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
