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

	public function normalizationValueDataProvider()
	{
		return array(
			'integer' => array(
				1,
				1,
			),
			'integer zero' => array(
				0,
				0,
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

	public function setValueWithInheritanceDataProvider()
	{
		return array(
			'global, integer' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array(2, 1),
			),
			'working copy, integer' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array(2, 1),
			),
		);
	}

	public function storageDataProvider()
	{
		return array(
			'integer' => array(1, 1),
		);
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
		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$ret = 1;
		}
		else {
			$ret = 2;
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
