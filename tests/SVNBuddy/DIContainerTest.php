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


use ConsoleHelpers\SVNBuddy\DIContainer;

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
			array('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager', 'cache_manager'),
			array('ConsoleHelpers\\SVNBuddy\\Config\\ConfigEditor', 'config_editor'),
			array('ConsoleHelpers\\SVNBuddy\\Helper\\ContainerHelper', 'container_helper'),
			array('ConsoleHelpers\\SVNBuddy\\Helper\\DateHelper', 'date_helper'),
			array('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\MergeSourceDetectorAggregator', 'merge_source_detector'),
			array('ConsoleHelpers\\SVNBuddy\\Process\\ProcessFactory', 'process_factory'),
			array('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector', 'repository_connector'),
			array('ConsoleHelpers\\SVNBuddy\\Repository\\Parser\\RevisionListParser', 'revision_list_parser'),
			array('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\RevisionLogFactory', 'revision_log_factory'),
			array('Symfony\\Component\\Console\\Helper\\HelperSet', 'helper_set'),
			array('Symfony\\Component\\Console\\Input\\ArgvInput', 'input'),
			array('Symfony\\Component\\Console\\Output\\ConsoleOutput', 'output'),
			array('ConsoleHelpers\\SVNBuddy\\ConsoleIO', 'io'),
			array('ConsoleHelpers\\SVNBuddy\\InteractiveEditor', 'editor'),
		);
	}

}
