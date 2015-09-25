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


use aik099\SVNBuddy\Config\ConfigEditor;
use Mockery as m;

class ConfigEditorTest extends WorkingDirectoryAwareTestCase
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
			'group1.setting1' => 'sub-value1',
			'group1.sub-group1.setting1' => 'sub-value2',
		);

		$config_editor = $this->createConfigEditor($settings);

		$this->assertEquals('top-value1', $config_editor->get('setting1'));
		$this->assertEquals('sub-value1', $config_editor->get('group1.setting1'));
		$this->assertEquals(
			array('group1.sub-group1.setting1' => 'sub-value2'),
			$config_editor->get('group1.sub-group1.')
		);
		$this->assertNull($config_editor->get('non-existing-setting'));
		$this->assertEquals('user default', $config_editor->get('non-existing-setting', 'user default'));
	}

	public function testSet()
	{
		$config_editor = $this->createConfigEditor(array());
		$config_editor->set('setting1', 'value1');
		$config_editor->set('top.sub1.sub11', 'one');
		$config_editor->set('top.sub1.sub12', 'two');

		$this->assertEquals('value1', $config_editor->get('setting1'));
		$this->assertEquals('one', $config_editor->get('top.sub1.sub11'));
		$this->assertEquals('two', $config_editor->get('top.sub1.sub12'));
		$this->assertEquals(
			array('top.sub1.sub11' => 'one', 'top.sub1.sub12' => 'two'),
			$config_editor->get('top.sub1.')
		);
	}

	public function testDelete()
	{
		$config_editor = $this->createConfigEditor(array(
			'top.sub1.sub11' => 'one',
			'top.sub1.sub12' => 'two',
		));

		$this->assertEquals('one', $config_editor->get('top.sub1.sub11'));
		$config_editor->set('top.sub1.sub11', null);
		$this->assertEquals('default-value', $config_editor->get('top.sub1.sub11', 'default-value'));
	}

	/**
	 * @dataProvider configDefaultsDataProvider
	 */
	public function testConfigDefaults($default_name, $default_value)
	{
		$config_editor = new ConfigEditor($this->configPath);

		$this->assertSame($default_value, $config_editor->get($default_name));
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
		new ConfigEditor($this->configPath);
		$this->assertFileExists($this->configPath, 'config with defaults is automatically created');
	}

	/**
	 * Creates config editor instance with given settings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return ConfigEditor
	 */
	protected function createConfigEditor(array $settings)
	{
		file_put_contents($this->configPath, json_encode($settings));

		return new ConfigEditor($this->configPath);
	}

}
