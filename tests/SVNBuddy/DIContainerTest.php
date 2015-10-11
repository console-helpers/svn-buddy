<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy;


use aik099\SVNBuddy\DIContainer;

class DIContainerTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @dataProvider instanceDataProvider
	 */
	public function testInstance($class, $key)
	{
		$container = new DIContainer();

		$this->assertInstanceOf($class, $container[$key]);
	}

	public function instanceDataProvider()
	{
		return array(
			array('aik099\\SVNBuddy\\Cache\\CacheManager', 'cache_manager'),
			array('aik099\\SVNBuddy\\Config\\ConfigEditor', 'config_editor'),
			array('aik099\\SVNBuddy\\Helper\\ContainerHelper', 'container_helper'),
			array('aik099\\SVNBuddy\\Helper\\DateHelper', 'date_helper'),
			array('aik099\\SVNBuddy\\MergeSourceDetector\\MergeSourceDetectorAggregator', 'merge_source_detector'),
			array('aik099\\SVNBuddy\\Process\\ProcessFactory', 'process_factory'),
			array('aik099\\SVNBuddy\\RepositoryConnector\\RepositoryConnector', 'repository_connector'),
			array('aik099\\SVNBuddy\\RepositoryConnector\\RevisionListParser', 'revision_list_parser'),
			array('aik099\\SVNBuddy\\RepositoryConnector\\RevisionLogFactory', 'revision_log_factory'),
			array('Symfony\\Component\\Console\\Helper\\HelperSet', 'helper_set'),
			array('Symfony\\Component\\Console\\Input\\ArgvInput', 'input'),
			array('Symfony\\Component\\Console\\Output\\ConsoleOutput', 'output'),
			array('aik099\\SVNBuddy\\ConsoleIO', 'io'),
			array('aik099\\SVNBuddy\\InteractiveEditor', 'editor'),
		);
	}

}
