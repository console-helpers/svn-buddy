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

	public function normalizationValueDataProvider($test_name, $value = array('a'), $normalized_value = array('b'))
	{
		$value = $this->getSampleValue($value, true);
		$normalized_value = $this->getSampleValue($normalized_value, true);

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

	public function setValueWithInheritanceDataProvider($test_name, $wc_value = array('global_value'), $global_value = array('default'))
	{
		$wc_value = $this->getSampleValue($wc_value);
		$global_value = $this->getSampleValue($global_value);

		return array(
			'global, array' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array($wc_value, $global_value),
			),
			'working copy, array' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array($wc_value, $global_value),
			),
		);
	}

	public function storageDataProvider($test_name, $default_value = array('a'), $stored_value = array('b'))
	{
		$default_value = $this->getSampleValue($default_value, true);
		$stored_value = $this->getSampleValue($stored_value, true);

		return array(
			'array into string' => array(array($default_value, $stored_value), $default_value . PHP_EOL . $stored_value),
			'array as string' => array($default_value . PHP_EOL . $stored_value, $default_value . PHP_EOL . $stored_value),
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
