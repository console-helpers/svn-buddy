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

	/**
	 * Config file path.
	 *
	 * @var string
	 */
	protected $configPath;

	protected function setUp()
	{
		parent::setUp();

		$this->configPath = $this->getWorkingDirectory() . '/test_config.json';
	}

	public function testGet()
	{
		$settings = array(
			'setting1' => 'top-value1',
			'group1' => array(
				'setting1' => 'sub-value1',
				'sub-group1' => array(
					'setting1' => 'sub-value2',
				),
			),
		);

		$config = $this->createConfig($settings);

		$this->assertEquals('top-value1', $config->get('setting1'));
		$this->assertEquals('sub-value1', $config->get('group1.setting1'));
		$this->assertEquals(array('setting1' => 'sub-value2'), $config->get('group1.sub-group1'));
		$this->assertNull($config->get('non-existing-setting'));
		$this->assertEquals('user default', $config->get('non-existing-setting', 'user default'));
	}

	public function testSet()
	{
		$config = $this->createConfig(array());
		$config->set('setting1', 'value1');
		$config->set('top.sub1.sub11', 'one');
		$config->set('top.sub1.sub12', 'two');

		$this->assertEquals('value1', $config->get('setting1'));
		$this->assertEquals('one', $config->get('top.sub1.sub11'));
		$this->assertEquals('two', $config->get('top.sub1.sub12'));
		$this->assertEquals(array('sub11' => 'one', 'sub12' => 'two'), $config->get('top.sub1'));
	}

	/**
	 * @dataProvider configDefaultsDataProvider
	 */
	public function testConfigDefaults($default_name, $default_value)
	{
		$config = new Config($this->configPath);

		$this->assertSame($default_value, $config->get($default_name));
	}

	public function configDefaultsDataProvider()
	{
		return array(
			'setting:repository-connector.username' => array('repository-connector.username', ''),
			'setting:repository-connector.password' => array('repository-connector.password', ''),
		);
	}

	public function testConfigFileCreation()
	{
		$this->assertFileNotExists($this->configPath, 'config file doesn\'t exist initially');
		new Config($this->configPath);
		$this->assertFileExists($this->configPath, 'config with defaults is automatically created');
	}

	/**
	 * Creates config instance with given settings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return Config
	 */
	protected function createConfig(array $settings)
	{
		file_put_contents($this->configPath, json_encode($settings));

		return new Config($this->configPath);
	}

}
