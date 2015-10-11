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


use aik099\SVNBuddy\Config\AbstractConfigSetting;

class IntegerConfigSettingTest extends AbstractConfigSettingTest
{

	protected function setUp()
	{
		if ( !isset($this->className) ) {
			$this->className = 'aik099\\SVNBuddy\\Config\\IntegerConfigSetting';
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = 0;
		}

		parent::setUp();
	}

	public function normalizationValueDataProvider($test_name, $a_value = 1, $b_value = 0)
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'integer' => array(
				$a_value,
				$a_value,
			),
			'integer zero' => array(
				$b_value,
				$b_value,
			),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be an integer.
	 * @dataProvider sampleStringDataProvider
	 */
	public function testSetValueStringToInteger($value)
	{
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
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "name" config setting value must be an integer.
	 * @dataProvider sampleArrayDataProvider
	 */
	public function testSetValueArrayToInteger($value)
	{
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

	public function setValueWithInheritanceDataProvider($test_name, $a_value = 2, $b_value = 1)
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'global, integer' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array($a_value, $b_value),
			),
			'working copy, integer' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array($a_value, $b_value),
			),
		);
	}

	public function storageDataProvider($test_name, $a_value = 1, $b_value = 1)
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'integer' => array($a_value, $a_value),
		);
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param mixed $scope_bit Scope bit.
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
