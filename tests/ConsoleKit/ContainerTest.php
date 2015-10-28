<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\ConsoleKit;


use ConsoleHelpers\ConsoleKit\Container;

class ContainerTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @dataProvider instanceDataProvider
	 */
	public function testInstance($class, $key)
	{
		$container = $this->createContainer();

		if ( is_string($class) && strpos($class, '\\') !== false ) {
			$this->assertInstanceOf($class, $container[$key]);
		}
		else {
			$this->assertEquals($class, $container[$key]);
		}
	}

	public function instanceDataProvider()
	{
		return array(
			'app_name' => array('UNKNOWN', 'app_name'),
			'app_version' => array('UNKNOWN', 'app_version'),
			'config_file' => array('{base}/config.json', 'config_file'),
			'config_defaults' => array(array(), 'config_defaults'),
			'working_directory_sub_folder' => array('.console-kit', 'working_directory_sub_folder'),
			'config_editor' => array('ConsoleHelpers\\ConsoleKit\\Config\\ConfigEditor', 'config_editor'),
			'input' => array('Symfony\\Component\\Console\\Input\\ArgvInput', 'input'),
			'output' => array('Symfony\\Component\\Console\\Output\\ConsoleOutput', 'output'),
			'io' => array('ConsoleHelpers\\ConsoleKit\\ConsoleIO', 'io'),
			'helper_set' => array('Symfony\\Component\\Console\\Helper\\HelperSet', 'helper_set'),
			'container_helper' => array('ConsoleHelpers\\ConsoleKit\\Helper\\ContainerHelper', 'container_helper'),
		);
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
