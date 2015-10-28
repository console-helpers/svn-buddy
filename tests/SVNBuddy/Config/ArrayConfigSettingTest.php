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

class ArrayConfigSettingTest extends AbstractConfigSettingTest
{

	protected function setUp()
	{
		if ( !isset($this->className) ) {
			$this->className = 'ConsoleHelpers\\SVNBuddy\\Config\\ArrayConfigSetting';
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = array();
		}

		parent::setUp();
	}

	public function normalizationValueDataProvider($test_name, $a_value = array('a'), $b_value = array('b'))
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

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
				array($a_value, $b_value),
				array($a_value, $b_value),
			),
			'array as scalar' => array(
				$a_value . PHP_EOL . $b_value,
				array($a_value, $b_value),
			),
			'array with empty value' => array(
				array($a_value, '', $b_value, ''),
				array($a_value, $b_value),
			),
			'array with empty value as scalar' => array(
				$a_value . PHP_EOL . PHP_EOL . $b_value . PHP_EOL,
				array($a_value, $b_value),
			),
			'array each element trimmed' => array(
				array(' ' . $a_value . ' ', ' ' . $b_value . ' '),
				array($a_value, $b_value),
			),
			'array each element trimmed as scalar' => array(
				' ' . $a_value . ' ' . PHP_EOL . ' ' . $b_value . ' ',
				array($a_value, $b_value),
			),
		);
	}

	public function setValueWithInheritanceDataProvider($test_name, $a_value = array('global_value'), $b_value = array('default'))
	{
		$a_value = $this->getSampleValue($a_value);
		$b_value = $this->getSampleValue($b_value);

		return array(
			'global, array' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array($a_value, $b_value),
			),
			'working copy, array' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array($a_value, $b_value),
			),
		);
	}

	public function storageDataProvider($test_name, $a_value = array('a'), $b_value = array('b'))
	{
		$a_value = $this->getSampleValue($a_value, true);
		$b_value = $this->getSampleValue($b_value, true);

		return array(
			'array into string' => array(array($a_value, $b_value), $a_value . PHP_EOL . $b_value),
			'array as string' => array($a_value . PHP_EOL . $b_value, $a_value . PHP_EOL . $b_value),
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
			$ret = array('OK');
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = array('G_OK');
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
		return implode(PHP_EOL, $value);
	}

}
