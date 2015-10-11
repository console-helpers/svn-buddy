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
use Tests\aik099\SVNBuddy\ProphecyToken\ConfigStorageNameToken;

class ConfigSettingTest extends AbstractConfigSettingTest
{

	protected function setUp()
	{
		$this->defaultDataType = ConfigSetting::TYPE_STRING;
		$this->defaultValue = 'default';

		parent::setUp();
	}

	/**
	 * @dataProvider dataTypeDataProvider
	 */
	public function testCreateWithCorrectDataType($data_type)
	{
		$this->createConfigSetting(null, $data_type, false);

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

	/**
	 * @dataProvider setValueWithInheritanceDataProvider
	 */
	public function testSetValueWithInheritance($scope_bit, $data_type, array $defaults)
	{
		$wc_value = $defaults[0];
		$global_value = $defaults[1];

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$this->configEditor
				->get(
					new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($global_value)
				)
				->willReturn(
					$this->convertToStorage($wc_value)
				)
				->shouldBeCalled();

			$config_setting = $this->createConfigSetting($scope_bit, $data_type, true, $global_value);
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->set(new ConfigStorageNameToken('name', $scope_bit), null)
				->shouldBeCalled();

			$config_setting->setValue($wc_value);
		}
		else {
			$this->configEditor
				->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), $global_value)
				->shouldNotBeCalled();

			$config_setting = $this->createConfigSetting($scope_bit, $data_type, true, $global_value);

			$this->configEditor
				->set(new ConfigStorageNameToken('name', $scope_bit), null)
				->shouldBeCalled();

			$config_setting->setValue($global_value);
		}
	}

	public function setValueWithInheritanceDataProvider()
	{
		return array(
			'global, string' => array(
				ConfigSetting::SCOPE_GLOBAL,
				ConfigSetting::TYPE_STRING,
				array('global_value', 'default'),
			),
			'working copy, string' => array(
				ConfigSetting::SCOPE_WORKING_COPY,
				ConfigSetting::TYPE_STRING,
				array('global_value', 'default'),
			),
			'global, integer' => array(
				ConfigSetting::SCOPE_GLOBAL,
				ConfigSetting::TYPE_INTEGER,
				array(2, 1),
			),
			'working copy, integer' => array(
				ConfigSetting::SCOPE_WORKING_COPY,
				ConfigSetting::TYPE_INTEGER,
				array(2, 1),
			),
			'global, array' => array(
				ConfigSetting::SCOPE_GLOBAL,
				ConfigSetting::TYPE_ARRAY,
				array(array('global_value'), array('default')),
			),
			'working copy, array' => array(
				ConfigSetting::SCOPE_WORKING_COPY,
				ConfigSetting::TYPE_ARRAY,
				array(array('global_value'), array('default')),
			),
		);
	}

	/**
	 * @dataProvider storageDataProvider
	 */
	public function testStorage($user_value, $stored_value, $data_type)
	{
		$this->configEditor
			->set(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), $stored_value)
			->shouldBeCalled();

		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, $data_type);
		$config_setting->setValue($user_value);
	}

	/**
	 * @dataProvider storageDataProvider
	 */
	public function testDefaultValueIsConvertedToScalar($default_value, $stored_value, $data_type)
	{
		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL), $stored_value)
			->willReturn(null)
			->shouldBeCalled();

		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL, $data_type, true, $default_value);
		$config_setting->getValue(ConfigSetting::SCOPE_GLOBAL);
	}

	public function storageDataProvider()
	{
		return array(
			'array into string' => array(array('a', 'b'), 'a' . PHP_EOL . 'b', ConfigSetting::TYPE_ARRAY),
			'array as string' => array('a' . PHP_EOL . 'b', 'a' . PHP_EOL . 'b', ConfigSetting::TYPE_ARRAY),
			'integer' => array(1, 1, ConfigSetting::TYPE_INTEGER),
			'string' => array('a', 'a', ConfigSetting::TYPE_STRING),
		);
	}

}
