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

abstract class AbstractConfigSettingTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Config editor
	 *
	 * @var ObjectProphecy
	 */
	protected $configEditor;

	/**
	 * If config setting accepts multiple data types.
	 *
	 * @var boolean
	 */
	protected $acceptMultipleDateTypes = true;

	/**
	 * Default data type.
	 *
	 * @var integer
	 */
	protected $defaultDataType = null;

	/**
	 * Default value.
	 *
	 * @var string
	 */
	protected $defaultValue = null;

	protected function setUp()
	{
		parent::setUp();

		$this->configEditor = $this->prophesize('aik099\\SVNBuddy\Config\\ConfigEditor');
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
		$this->createConfigSetting(null, null, false)->getValue();
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
			->get(
				new ConfigStorageNameToken('name', $scope_bit),
				$this->convertToStorage($this->defaultValue)
			)
			->willReturn($this->getSampleValue($scope_bit, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue($scope_bit), $config_setting->getValue($scope_bit));
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
			->willReturn($this->getSampleValue($scope_bit, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue($scope_bit), $config_setting->getValue());
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
			->willReturn($this->getSampleValue(ConfigSetting::SCOPE_GLOBAL, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue(ConfigSetting::SCOPE_GLOBAL), $config_setting->getValue());
	}

	public function testGetValueFromGlobalWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL);

		$this->configEditor
			->get(new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL))
			->shouldBeCalled();

		$this->assertEquals($this->defaultValue, $config_setting->getValue());
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

		$this->assertEquals($this->defaultValue, $config_setting->getValue());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please use setEditor() before calling aik099\SVNBuddy\Config\ConfigSetting::setValue().
	 */
	public function testSetValueWithoutEditor()
	{
		$this->createConfigSetting(null, null, false)->setValue('value');
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
				->get(
					new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($this->defaultValue)
				)
				->shouldBeCalled();
		}

		$expected_value = $this->getSampleValue($scope_bit);

		if ( $this->defaultDataType === ConfigSetting::TYPE_ARRAY ) {
			$expected_value = reset($expected_value);
		}

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), $expected_value)
			->shouldBeCalled();

		$config_setting->setValue($this->getSampleValue($scope_bit));
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
				->get(
					new ConfigStorageNameToken('name', ConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($this->defaultValue)
				)
				->shouldBeCalled();
		}

		$expected_value = $this->getSampleValue($scope_bit);

		if ( $this->defaultDataType === ConfigSetting::TYPE_ARRAY ) {
			$expected_value = reset($expected_value);
		}

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), $expected_value)
			->shouldBeCalled();

		$config_setting->setValue($this->getSampleValue($scope_bit), $scope_bit);
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
		$this->createConfigSetting()->setValue($this->getSampleValue(ConfigSetting::SCOPE_WORKING_COPY));
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
	 * @param integer $scope_bit     Scope bit.
	 * @param int     $data_type     Data type.
	 * @param boolean $with_editor   Connect editor to the setting.
	 * @param mixed   $default_value Default value.
	 *
	 * @return ConfigSetting
	 */
	protected function createConfigSetting(
		$scope_bit = null,
		$data_type = null,
		$with_editor = true,
		$default_value = null
	) {
		if ( !isset($data_type) ) {
			$data_type = $this->defaultDataType;
		}

		if ( !isset($default_value) ) {
			$default_value = $this->defaultValue;
		}

		$config_setting = new ConfigSetting('name', $data_type, $default_value, $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor->reveal());
		}

		return $config_setting;
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param integer $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 */
	protected function getSampleValue($scope_bit, $as_stored = false)
	{
		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			return 'OK';
		}

		return 'G_OK';
	}

	/**
	 * Converts value to storage format.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function convertToStorage($value)
	{
		if ( is_array($value) ) {
			return implode(PHP_EOL, $value);
		}

		return $value;
	}

}
