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


use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\ChoiceConfigSetting;

class ChoiceConfigSettingTest extends AbstractConfigSettingTest
{

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		if ( !isset($this->className) ) {
			$this->className = ChoiceConfigSetting::class;
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = 1;
		}

		parent::setupTest();
	}

	public static function normalizationValueDataProvider($test_name, $value = 1, $normalized_value = 1)
	{
		$value = static::getSampleValue($value, true);
		$normalized_value = static::getSampleValue($normalized_value, true);

		return array(
			'as is' => array(
				$value,
				$normalized_value,
			),
		);
	}

	public function testSetValueUnknownChoice()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "name" config setting value must be one of "1", "2", "3".');

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue(5);
	}

	public static function setValueWithInheritanceDataProvider($test_name, $wc_value = 2, $global_value = 1)
	{
		$wc_value = static::getSampleValue($wc_value, true);
		$global_value = static::getSampleValue($global_value, true);

		return array(
			array($wc_value, $global_value),
		);
	}

	public static function storageDataProvider($test_name, $default_value = 1, $stored_value = 1)
	{
		$default_value = static::getSampleValue($default_value, true);
		$stored_value = static::getSampleValue($stored_value, true);

		return array(
			'as is' => array($default_value, $stored_value),
			'from option id' => array('two', 2),
		);
	}

	public function testGetChoices()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->assertEquals(array(1 => 'one', 2 => 'two', 3 => 'three'), $config_setting->getChoices());
	}

	public function testCreateWithEmptyChoices()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "$choices" parameter must not be empty.');

		new $this->className('name', array(), $this->defaultValue);
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param mixed   $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 */
	protected static function getSampleValue($scope_bit, $as_stored = false)
	{
		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$ret = 2;
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = 3;
		}
		else {
			$ret = $scope_bit;
		}

		return $as_stored ? static::convertToStorage($ret) : $ret;
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
		return (int)$value;
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
		$config_setting = new $class('name', array(1 => 'one', 2 => 'two', 3 => 'three'), $default_value, $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor);
		}

		return $config_setting;
	}

}
