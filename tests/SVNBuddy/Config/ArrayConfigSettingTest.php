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
use ConsoleHelpers\SVNBuddy\Config\ArrayConfigSetting;

class ArrayConfigSettingTest extends AbstractConfigSettingTest
{

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		if ( !isset($this->className) ) {
			$this->className = ArrayConfigSetting::class;
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = array();
		}

		parent::setupTest();
	}

	public static function normalizationValueDataProvider($test_name, $value = array('a'), $normalized_value = array('b'))
	{
		$value = static::getSampleValue($value, true);
		$normalized_value = static::getSampleValue($normalized_value, true);

		return array(
			'empty array' => array(
				array(),
				array(),
			),
			'empty array as scalar' => array(
				'',
				array(),
			),
			'array' => array(
				array($value, $normalized_value),
				array($value, $normalized_value),
			),
			'array as scalar' => array(
				$value . PHP_EOL . $normalized_value,
				array($value, $normalized_value),
			),
			'array with empty value' => array(
				array($value, '', $normalized_value, ''),
				array($value, $normalized_value),
			),
			'array with empty value as scalar' => array(
				$value . PHP_EOL . PHP_EOL . $normalized_value . PHP_EOL,
				array($value, $normalized_value),
			),
			'array each element trimmed' => array(
				array(' ' . $value . ' ', ' ' . $normalized_value . ' '),
				array($value, $normalized_value),
			),
			'array each element trimmed as scalar' => array(
				' ' . $value . ' ' . PHP_EOL . ' ' . $normalized_value . ' ',
				array($value, $normalized_value),
			),
		);
	}

	public static function setValueWithInheritanceDataProvider($test_name, $wc_value = array('global_value'), $global_value = array('default'))
	{
		$wc_value = static::getSampleValue($wc_value);
		$global_value = static::getSampleValue($global_value);

		return array(
			array($wc_value, $global_value),
		);
	}

	/**
	 * @dataProvider defaultValueIsConvertedToScalarDataProvider
	 */
	public function testDefaultValueIsConvertedToScalar($default_value, $user_value)
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL, $default_value);

		$this->assertSame($user_value, $config_setting->getValue(AbstractConfigSetting::SCOPE_GLOBAL));
	}

	public static function defaultValueIsConvertedToScalarDataProvider($test_name, $default_value = array('a'), $stored_value = array('b'))
	{
		$default_value = static::getSampleValue($default_value, true);
		$stored_value = static::getSampleValue($stored_value, true);

		return array(
			'array into string' => array(array($default_value, $stored_value), array($default_value, $stored_value)),
			'array as string' => array($default_value . PHP_EOL . $stored_value, array($default_value, $stored_value)),
		);
	}

	public static function storageDataProvider($test_name, $default_value = array('a'), $stored_value = array('b'))
	{
		$default_value = static::getSampleValue($default_value, true);
		$stored_value = static::getSampleValue($stored_value, true);

		return array(
			'array into string' => array(array($default_value, $stored_value), $default_value . PHP_EOL . $stored_value),
			'array as string' => array($default_value . PHP_EOL . $stored_value, $default_value . PHP_EOL . $stored_value),
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
			$ret = array('OK');
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = array('G_OK');
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
		return implode(PHP_EOL, $value);
	}

}
