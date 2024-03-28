<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Config;


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

abstract class AbstractConfigSettingTest extends AbstractTestCase
{

	/**
	 * Config editor
	 *
	 * @var ConfigEditor
	 */
	protected $configEditor;

	/**
	 * Class of config setting.
	 *
	 * @var string
	 */
	protected $className;

	/**
	 * Default value.
	 *
	 * @var string
	 */
	protected $defaultValue;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->configEditor = new ConfigEditor('php://memory');
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

	public static function scopeDataProvider()
	{
		return array(
			'global scope bit' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array(AbstractConfigSetting::SCOPE_GLOBAL => true, AbstractConfigSetting::SCOPE_WORKING_COPY => false),
			),
			'working copy scope bit' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array(AbstractConfigSetting::SCOPE_WORKING_COPY => true, AbstractConfigSetting::SCOPE_GLOBAL => true),
			),
			'no scope bit' => array(
				null,
				array(AbstractConfigSetting::SCOPE_WORKING_COPY => true, AbstractConfigSetting::SCOPE_GLOBAL => true),
			),
		);
	}

	/**
	 * @dataProvider incorrectScopeDataProvider
	 */
	public function testCreateWithIncorrectScope($scope)
	{
		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage('The $scope must be either "working copy" or "global".');

		$this->createConfigSetting($scope);
	}

	public static function incorrectScopeDataProvider()
	{
		return array(
			'empty scope bit' => array(0),
			'mixed scope bits' => array(
				AbstractConfigSetting::SCOPE_GLOBAL | AbstractConfigSetting::SCOPE_WORKING_COPY,
			),
		);
	}

	public function testGetName()
	{
		$config_setting = $this->createConfigSetting();

		$this->assertEquals('name', $config_setting->getName());
	}

	public function testGetValueWithoutEditor()
	{
		$this->expectException('LogicException');
		$this->expectExceptionMessage('Please use setEditor() before calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::getValue().');

		$this->createConfigSetting(null, false, null)->getValue();
	}

	public function testGetValueWithForbiddenScopeBit()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The usage of "2" scope bit for "name" config setting is forbidden.');

		$this
			->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL)
			->getValue(AbstractConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueWithCorrectScopeBit($scope_bit, $storage_setting_name)
	{
		$config_setting = $this->createConfigSetting();

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->assertSame(
			$this->defaultValue,
			$config_setting->getValue($scope_bit),
			'Default value is returned'
		);

		$expected = static::getSampleValue($scope_bit);
		$this->configEditor->set($storage_setting_name, $expected);

		$this->assertEquals(
			$expected,
			$config_setting->getValue($scope_bit),
			'Stored value is returned'
		);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueFromWithoutFallback($scope_bit, $storage_setting_name)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->assertSame(
			$this->defaultValue,
			$config_setting->getValue(),
			'Default value is returned'
		);

		$expected = static::getSampleValue($scope_bit);
		$this->configEditor->set($storage_setting_name, $expected);

		$this->assertEquals(
			$expected,
			$config_setting->getValue(),
			'Stored value is returned'
		);
	}

	public function testGetValueFromWorkingCopyWithFallbackToGlobal()
	{
		$config_setting = $this->createConfigSetting();
		$config_setting->setWorkingCopyUrl('url');

		$this->configEditor->set(
			'global-settings.name',
			static::getSampleValue(AbstractConfigSetting::SCOPE_GLOBAL, true)
		);

		$this->assertEquals(
			static::getSampleValue(AbstractConfigSetting::SCOPE_GLOBAL),
			$config_setting->getValue()
		);
	}

	public function testGetValueFromGlobalWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->assertEquals($this->defaultValue, $config_setting->getValue());
	}

	public function testSetValueWithoutEditor()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('Please use setEditor() before calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::setValue().');

		$this->createConfigSetting(null, false, null)->setValue('value');
	}

	public function testSetValueWithForbiddenScopeBit()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The usage of "2" scope bit for "name" config setting is forbidden.');

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue('value', AbstractConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithoutScopeBit($scope_bit, $storage_setting_name)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor->set(
				'global-settings.name',
				static::convertToStorage($this->defaultValue)
			);
		}

		$expected_value = static::getSampleValue($scope_bit);
		$config_setting->setValue($expected_value);

		$this->assertSame(static::convertToStorage($expected_value), $this->configEditor->get($storage_setting_name));
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithScopeBit($scope_bit, $storage_setting_name)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor->set(
				'global-settings.name',
				static::convertToStorage($this->defaultValue)
			);
		}

		$expected_value = static::getSampleValue($scope_bit);
		$config_setting->setValue($expected_value, $scope_bit);

		$this->assertSame(static::convertToStorage($expected_value), $this->configEditor->get($storage_setting_name));
	}

	public function testGetValueWithoutWorkingCopy()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('Please call setWorkingCopyUrl() prior to calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::getValue() method.');

		$this->createConfigSetting()->getValue();
	}

	public function testSetValueWithoutWorkingCopy()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('Please call setWorkingCopyUrl() prior to calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::setValue() method.');

		$this->createConfigSetting()->setValue(static::getSampleValue(AbstractConfigSetting::SCOPE_WORKING_COPY));
	}

	/**
	 * @dataProvider normalizationValueDataProvider
	 */
	public function testSetValueNormalization($value, $normalized_value)
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$config_setting->setValue($value);

		$this->assertSame($normalized_value, $config_setting->getValue());
	}

	public static function normalizationValueDataProvider($test_name, $value = null, $normalized_value = null)
	{
		throw new \RuntimeException('Override me.');
	}

	public static function scopeBitDataProvider()
	{
		return array(
			'working copy scope' => array(AbstractConfigSetting::SCOPE_WORKING_COPY, 'path-settings[url].name'),
			'global scope' => array(AbstractConfigSetting::SCOPE_GLOBAL, 'global-settings.name'),
		);
	}

	/**
	 * @dataProvider setValueWithInheritanceDataProvider
	 */
	public function testSetValueWithInheritanceFromGlobal($wc_value, $global_value)
	{
		$this->configEditor->set('global-settings.name', static::convertToStorage($wc_value));

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_WORKING_COPY, $global_value);
		$config_setting->setWorkingCopyUrl('url');

		$config_setting->setValue($wc_value);

		$this->assertNull($this->configEditor->get('path-settings[url].name'), 'Inherited value isn\'t stored');
	}

	/**
	 * @dataProvider setValueWithInheritanceDataProvider
	 */
	public function testSetValueWithInheritanceFromDefault($wc_value, $global_value)
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL, $global_value);

		$config_setting->setValue($global_value);

		$this->assertNull($this->configEditor->get('global-settings.name'), 'Inherited value isn\'t stored');
	}

	public static function setValueWithInheritanceDataProvider($test_name, $wc_value = null, $global_value = null)
	{
		throw new \RuntimeException('Override me.');
	}

	/**
	 * @dataProvider storageDataProvider
	 */
	public function testStorage($user_value, $stored_value)
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue($user_value);

		$this->assertSame($stored_value, $this->configEditor->get('global-settings.name'));
	}

	public static function storageDataProvider($test_name, $default_value = null, $stored_value = null)
	{
		throw new \RuntimeException('Override me.');
	}

	/**
	 * Creates config setting.
	 *
	 * @param integer $scope_bit     Scope bit.
	 * @param mixed   $default_value Default value.
	 * @param boolean $with_editor   Connect editor to the setting.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When config setting class not set.
	 */
	protected function createConfigSetting(
		$scope_bit = null,
		$default_value = null,
		$with_editor = true
	) {
		if ( !isset($default_value) ) {
			$default_value = $this->defaultValue;
		}

		if ( !isset($this->className) ) {
			throw new \LogicException('Please specify "$this->className" first.');
		}

		$class = $this->className;
		$config_setting = new $class('name', $default_value, $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor);
		}

		return $config_setting;
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param mixed   $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 * @throws \RuntimeException When method wasn't overridden.
	 */
	protected static function getSampleValue($scope_bit, $as_stored = false)
	{
		throw new \RuntimeException('Override me.');
	}

	/**
	 * Converts value to storage format.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected static function convertToStorage($value)
	{
		return $value;
	}

}
