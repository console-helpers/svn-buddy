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
use ConsoleHelpers\SVNBuddy\Config\IntegerConfigSetting;

class IntegerConfigSettingTest extends AbstractConfigSettingTestCase
{

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		if ( !isset($this->className) ) {
			$this->className = IntegerConfigSetting::class;
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = 0;
		}

		parent::setupTest();
	}

	public static function normalizationValueDataProvider()
	{
		$value = static::getSampleValue(1, true);
		$normalized_value = static::getSampleValue(0, true);

		return array(
			'integer' => array(
				$value,
				$value,
			),
			'integer zero' => array(
				$normalized_value,
				$normalized_value,
			),
		);
	}

	/**
	 * @dataProvider sampleStringDataProvider
	 */
	public function testSetValueStringToInteger($value)
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "name" config setting value must be an integer.');

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue($value);
	}

	public static function sampleStringDataProvider()
	{
		return array(
			'empty string' => array(''),
			'non-empty string' => array('a'),
		);
	}

	/**
	 * @dataProvider sampleArrayDataProvider
	 */
	public function testSetValueArrayToInteger($value)
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "name" config setting value must be an integer.');

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue($value);
	}

	public static function sampleArrayDataProvider()
	{
		return array(
			'empty array' => array(array()),
			'non-empty array' => array(array(1)),
		);
	}

	public static function setValueWithInheritanceDataProvider()
	{
		$wc_value = static::getSampleValue(2, true);
		$global_value = static::getSampleValue(1, true);

		return array(
			array($wc_value, $global_value),
		);
	}

	public static function storageDataProvider()
	{
		$default_value = static::getSampleValue(1, true);
		$stored_value = static::getSampleValue(1, true);

		return array(
			'integer' => array($default_value, $default_value),
		);
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
			$ret = 1;
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = 2;
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

}
