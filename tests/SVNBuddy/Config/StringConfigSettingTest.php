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
use ConsoleHelpers\SVNBuddy\Config\StringConfigSetting;

class StringConfigSettingTest extends AbstractConfigSettingTestCase
{

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		if ( !isset($this->className) ) {
			$this->className = StringConfigSetting::class;
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = '';
		}

		parent::setupTest();
	}

	public static function normalizationValueDataProvider($test_name, $value = 'a', $normalized_value = 'b')
	{
		$value = static::getSampleValue($value, true);
		$normalized_value = static::getSampleValue($normalized_value, true);

		return array(
			'empty string' => array(
				'',
				'',
			),
			'one line string' => array(
				$value,
				$value,
			),
			'one line string trimmed' => array(
				' ' . $value . ' ',
				$value,
			),
			'multi-line string' => array(
				$value . PHP_EOL . $normalized_value,
				$value . PHP_EOL . $normalized_value,
			),
			'multi-line string trimmed' => array(
				' ' . $value . PHP_EOL . $normalized_value . ' ',
				$value . PHP_EOL . $normalized_value,
			),
		);
	}

	/**
	 * @dataProvider sampleArrayDataProvider
	 */
	public function testSetValueArrayToString($value)
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "name" config setting value must be a string.');

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

	public static function setValueWithInheritanceDataProvider($test_name, $wc_value = 'global_value', $global_value = 'default')
	{
		$wc_value = static::getSampleValue($wc_value, true);
		$global_value = static::getSampleValue($global_value, true);

		return array(
			array($wc_value, $global_value),
		);
	}

	public static function storageDataProvider($test_name, $default_value = 'a', $stored_value = 'b')
	{
		$default_value = static::getSampleValue($default_value, true);
		$stored_value = static::getSampleValue($stored_value, true);

		return array(
			'string' => array($default_value, $default_value),
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
			$ret = 'OK';
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = 'G_OK';
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
		return trim($value);
	}

}
