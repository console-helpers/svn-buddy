<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Config;


use aik099\SVNBuddy\Config\ConfigSetting;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\aik099\SVNBuddy\ProphecyToken\ConfigStorageNameToken;

class ConfigSettingTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Config editor
	 *
	 * @var ObjectProphecy
	 */
	protected $configEditor;

	protected function setUp()
	{
		parent::setUp();

		$this->configEditor = $this->prophesize('aik099\\SVNBuddy\Config\\ConfigEditor');
	}

	/**
	 * @dataProvider dataTypeDataProvider
	 */
	public function testCreateWithCorrectDataType($data_type)
	{
		new ConfigSetting('name', $data_type, null);

		$this->assertTrue(true);
	}

	public function dataTypeDataProvider()
	{
		return array(
			'array type' => array(ConfigSetting::TYPE_ARRAY),
			'integer type' => array(ConfigSetting::TYPE_INTEGER),
			'string type' => array(ConfigSetting::TYPE_STRING),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "float" is not valid config setting data type.
	 */
	public function testCreateWithIncorrectDataType()
	{
		new ConfigSetting('name', 'float', null);
	}

	/**
	 * @dataProvider scopeDataProvider
	 */
	public function testCreateWithCorrectScope($scope_bit, array $scope_bits)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		foreach ( $scope_bits as $check_scope_bit => $check_value ) {
			$this->assertSame(
				$check_value,
				$config_setting->isWithinScope($check_scope_bit),
				'The "' . $scope_bit . '" expands into "' . $check_scope_bit . '" scope bit.'
			);
		}
	}

	public function scopeDataProvider()
	{
		return array(
			'global scope bit' => array(
				ConfigSetting::SCOPE_GLOBAL,
				array(ConfigSetting::SCOPE_GLOBAL => true, ConfigSetting::SCOPE_WORKING_COPY => false),
			),
			'working copy scope bit' => array(
				ConfigSetting::SCOPE_WORKING_COPY,
				array(ConfigSetting::SCOPE_WORKING_COPY => true, ConfigSetting::SCOPE_GLOBAL => true),
			),
			'no scope bit' => array(
				null,
				array(ConfigSetting::SCOPE_WORKING_COPY => true, ConfigSetting::SCOPE_GLOBAL => true),
			),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The $scope must be either "working copy" or "global".
	 * @dataProvider incorrectScopeDataProvider
	 */
	public function testCreateWithIncorrectScope($scope)
	{
		$this->createConfigSetting($scope);
	}

	public function incorrectScopeDataProvider()
	{
		return array(
			'empty scope bit' => array(0),
			'mixed scope bits' => array(ConfigSetting::SCOPE_GLOBAL | ConfigSetting::SCOPE_WORKING_COPY),
		);
	}

	public function testGetName()
	{
		$config_setting = $this->createConfigSetting();

		$this->assertEquals('name', $config_setting->getName());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please use setEditor() before calling aik099\SVNBuddy\Config\ConfigSetting::getValue().
	 */
	public function testGetValueWithoutEditor()
	{
		$this->createConfigSetting(null, ConfigSetting::TYPE_STRING, false)->getValue();
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The usage of "2" scope bit for "name" config setting is forbidden.
	 */
	public function testGetValueWithForbiddenScopeBit()
	{
		$this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL)->getValue(ConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueWithCorrectScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting();

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->configEditor
			->get(new ConfigStorageNameToken('name', $scope_bit), 'default')
			->willReturn('OK')
			->shouldBeCalled();

		$this->assertEquals('OK', $config_setting->getValue($scope_bit));
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueFromWithoutFallback($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->configEditor
			->get(new ConfigStorageNameToken('name', $scope_bit))
			->willReturn('OK')
			->shouldBeCalled();

		$this->assertEquals('OK', $config_setting->getValue());
	}

	public function testGetValueFromWorkingCopyWithFallbackToGlobal()
	{
		$config_setting = $this->createConfigSetting();
		$config_setting->setWorkingCopyUrl('url');

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_WORKING_COPY))
			->shouldBeCalled();

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL))
			->willReturn('G_OK')
			->shouldBeCalled();

		$this->assertEquals('G_OK', $config_setting->getValue());
	}

	public function testGetValueFromGlobalWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL);

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL))
			->shouldBeCalled();

		$this->assertEquals('default', $config_setting->getValue());
	}

	public function testGetValueFromWorkingCopyWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting();
		$config_setting->setWorkingCopyUrl('url');

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_WORKING_COPY))
			->shouldBeCalled();

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL))
			->shouldBeCalled();

		$this->assertEquals('default', $config_setting->getValue());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please use setEditor() before calling aik099\SVNBuddy\Config\ConfigSetting::setValue().
	 */
	public function testSetValueWithoutEditor()
	{
		$this->createConfigSetting(null, ConfigSetting::TYPE_STRING, false)->setValue('value');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The usage of "2" scope bit for "name" config setting is forbidden.
	 */
	public function testSetValueWithForbiddenScopeBit()
	{
		$this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL)->setValue('value', ConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithoutScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), 'default')
				->shouldBeCalled();
		}

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), 'value')
			->shouldBeCalled();

		$config_setting->setValue('value');
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), 'default')
				->shouldBeCalled();
		}

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), 'value')
			->shouldBeCalled();

		$config_setting->setValue('value', $scope_bit);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithInheritance($scope_bit)
	{
		$global_value_prediction = $this->configEditor->get(
			new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL),
			'default'
		);

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$default_value = 'global_value';
			$global_value_prediction->willReturn($default_value)->shouldBeCalled();
		}
		else {
			$default_value = 'default';
			$global_value_prediction->shouldNotBeCalled();
		}

		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), null)
			->shouldBeCalled();

		$config_setting->setValue($default_value);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please call setWorkingCopyUrl() prior to calling aik099\SVNBuddy\Config\ConfigSetting::getValue() method.
	 */
	public function testGetValueWithoutWorkingCopy()
	{
		$this->createConfigSetting()->getValue();
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please call setWorkingCopyUrl() prior to calling aik099\SVNBuddy\Config\ConfigSetting::setValue() method.
	 */
	public function testSetValueWithoutWorkingCopy()
	{
		$this->createConfigSetting()->setValue('value');
	}

	/**
	 * @dataProvider normalizationValueDataProvider
	 */
	public function testSetValueNormalization($data_type, $value, $normalized_value)
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, $data_type);

		$this->configEditor
			->set(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), Argument::any())
			->will(function (array $args, $config_editor) {
				$config_editor->get($args[0])->willReturn($args[1])->shouldBeCalled();
			})
			->shouldBeCalled();

		$config_setting->setValue($value);

		$this->assertSame($normalized_value, $config_setting->getValue());
	}

	public function normalizationValueDataProvider()
	{
		return array(
			// Arrays.
			'empty array' => array(
				ConfigSetting::TYPE_ARRAY,
				array(),
				array(),
			),
			'empty array as scalar' => array(
				ConfigSetting::TYPE_ARRAY,
				'',
				array(),
			),
			'array' => array(
				ConfigSetting::TYPE_ARRAY,
				array('a', 'b'),
				array('a', 'b'),
			),
			'array as scalar' => array(
				ConfigSetting::TYPE_ARRAY,
				'a' . PHP_EOL . 'b',
				array('a', 'b'),
			),
			'array with empty value' => array(
				ConfigSetting::TYPE_ARRAY,
				array('a', '', 'b', ''),
				array('a', 'b'),
			),
			'array with empty value as scalar' => array(
				ConfigSetting::TYPE_ARRAY,
				'a' . PHP_EOL . PHP_EOL . 'b' . PHP_EOL,
				array('a', 'b'),
			),
			'array each element trimmed' => array(
				ConfigSetting::TYPE_ARRAY,
				array(' a ', ' b '),
				array('a', 'b'),
			),
			'array each element trimmed as scalar' => array(
				ConfigSetting::TYPE_ARRAY,
				' a ' . PHP_EOL . ' b ',
				array('a', 'b'),
			),

			// String.
			'empty string' => array(
				ConfigSetting::TYPE_STRING,
				'',
				'',
			),
			'one line string' => array(
				ConfigSetting::TYPE_STRING,
				'a',
				'a',
			),
			'one line string trimmed' => array(
				ConfigSetting::TYPE_STRING,
				' a ',
				'a',
			),
			'multi-line string' => array(
				ConfigSetting::TYPE_STRING,
				'a' . PHP_EOL . 'b',
				'a' . PHP_EOL . 'b',
			),
			'multi-line string trimmed' => array(
				ConfigSetting::TYPE_STRING,
				' a' . PHP_EOL . 'b ',
				'a' . PHP_EOL . 'b',
			),

			// Integers.
			'integer' => array(
				ConfigSetting::TYPE_INTEGER,
				1,
				1,
			),
			'integer zero' => array(
				ConfigSetting::TYPE_INTEGER,
				0,
				0,
			),
			'integer trimmed' => array(
				ConfigSetting::TYPE_INTEGER,
				' 1 ',
				1,
			),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be a string.
	 * @dataProvider sampleArrayDataProvider
	 */
	public function testSetValueArrayToString($value)
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, ConfigSetting::TYPE_STRING);
		$config_setting->setValue($value);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be an integer.
	 * @dataProvider sampleArrayDataProvider
	 */
	public function testSetValueArrayToInteger($value)
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, ConfigSetting::TYPE_INTEGER);
		$config_setting->setValue($value);
	}

	public function sampleArrayDataProvider()
	{
		return array(
			'empty array' => array(array()),
			'non-empty array' => array(array(1)),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be an integer.
	 * @dataProvider sampleStringDataProvider
	 */
	public function testSetValueStringToInteger($value)
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, ConfigSetting::TYPE_INTEGER);
		$config_setting->setValue($value);
	}

	public function sampleStringDataProvider()
	{
		return array(
			'empty string' => array(''),
			'non-empty string' => array('a'),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be a string.
	 * @dataProvider sampleIntegerDataProvider
	 */
	public function testSetValueIntegerToString($value)
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, ConfigSetting::TYPE_STRING);
		$config_setting->setValue($value);
	}

	public function sampleIntegerDataProvider()
	{
		return array(
			'empty number' => array(0),
			'non-empty number' => array(1),
		);
	}

	public function scopeBitDataProvider()
	{
		return array(
			'working copy scope' => array(ConfigSetting::SCOPE_WORKING_COPY),
			'global scope' => array(ConfigSetting::SCOPE_GLOBAL),
		);
	}

	/**
	 * Creates config setting.
	 *
	 * @param integer $scope_bit   Scope bit.
	 * @param int     $data_type   Data type.
	 * @param boolean $with_editor Connect editor to the setting.
	 *
	 * @return ConfigSetting
	 */
	protected function createConfigSetting(
		$scope_bit = null,
		$data_type = ConfigSetting::TYPE_STRING,
		$with_editor = true
	) {
		$config_setting = new ConfigSetting('name', $data_type, 'default', $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor->reveal());
		}

		return $config_setting;
	}

}
