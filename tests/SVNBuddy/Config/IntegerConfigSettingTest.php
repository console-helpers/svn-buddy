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
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;
use ConsoleHelpers\SVNBuddy\Config\IntegerConfigSetting;

class IntegerConfigSettingTest extends AbstractConfigSettingTest
{

	use ExpectException;

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

	public function normalizationValueDataProvider($test_name, $value = 1, $normalized_value = 0)
	{
		$value = $this->getSampleValue($value, true);
		$normalized_value = $this->getSampleValue($normalized_value, true);

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

	public function sampleStringDataProvider()
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

	public function sampleArrayDataProvider()
	{
		return array(
			'empty array' => array(array()),
			'non-empty array' => array(array(1)),
		);
	}

	public function setValueWithInheritanceDataProvider($test_name, $wc_value = 2, $global_value = 1)
	{
		$wc_value = $this->getSampleValue($wc_value, true);
		$global_value = $this->getSampleValue($global_value, true);

		return array(
			array($wc_value, $global_value),
		);
	}

	public function storageDataProvider($test_name, $default_value = 1, $stored_value = 1)
	{
		$default_value = $this->getSampleValue($default_value, true);
		$stored_value = $this->getSampleValue($stored_value, true);

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
	protected function getSampleValue($scope_bit, $as_stored = false)
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

		return $as_stored ? $this->convertToStorage($ret) : $ret;
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
		return (int)$value;
	}

}
