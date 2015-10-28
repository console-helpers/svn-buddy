<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Config;


use aik099\SVNBuddy\Config\AbstractConfigSetting;

class StringConfigSettingTest extends AbstractConfigSettingTest
{

	protected function setUp()
	{
		if ( !isset($this->className) ) {
			$this->className = 'aik099\\SVNBuddy\\Config\\StringConfigSetting';
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = '';
		}

		parent::setUp();
	}

	public function normalizationValueDataProvider($test_name, $a_value = 'a', $b_value = 'b')
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'empty string' => array(
				'',
				'',
			),
			'one line string' => array(
				$a_value,
				$a_value,
			),
			'one line string trimmed' => array(
				' ' . $a_value . ' ',
				$a_value,
			),
			'multi-line string' => array(
				$a_value . PHP_EOL . $b_value,
				$a_value . PHP_EOL . $b_value,
			),
			'multi-line string trimmed' => array(
				' ' . $a_value . PHP_EOL . $b_value . ' ',
				$a_value . PHP_EOL . $b_value,
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

	public function setValueWithInheritanceDataProvider($test_name, $a_value = 'global_value', $b_value = 'default')
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'global, string' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array($a_value, $b_value),
			),
			'working copy, string' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array($a_value, $b_value),
			),
		);
	}

	public function storageDataProvider($test_name, $a_value = 'a', $b_value = 'b')
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'string' => array($a_value, $a_value),
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
			$ret = 'OK';
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = 'G_OK';
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
		return trim($value);
	}

}
