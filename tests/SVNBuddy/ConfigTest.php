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


use aik099\SVNBuddy\Config;
use Mockery as m;

class ConfigTest extends WorkingDirectoryAwareTestCase
{

	public function testGet()
	{
		$config = new Config(array('setting1' => 'value1'), '');

		$this->assertEquals('value1', $config->get('setting1'));
		$this->assertFalse($config->get('non-existing-setting'));
		$this->assertEquals('user default', $config->get('non-existing-setting', 'user default'));
	}

	/**
	 * @dataProvider configDefaultsDataProvider
	 */
	public function testConfigDefaults($default_name, $default_value)
	{
		$config = new Config(array('setting1' => 'value1'), '');

		$this->assertSame($default_value, $config->get($default_name));
	}

	public function configDefaultsDataProvider()
	{
		return array(
			'setting:svn-username' => array('svn-username', ''),
			'setting:svn-password' => array('svn-password', ''),
		);
	}

	public function testConfigFileCreation()
	{
		$home_folder = $this->getWorkingDirectory();
		$config_path = $home_folder . '/test_config.json';
		$this->assertFileNotExists($config_path);

		Config::createFromFile('{base}/test_config.json', $home_folder);
		$this->assertFileExists($config_path);

		// If config file will be created again with existing config we'll get a warning here.
		Config::createFromFile('{base}/test_config.json', $home_folder);
	}

}
