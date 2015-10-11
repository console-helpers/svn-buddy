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

class ArrayConfigSettingTest extends AbstractConfigSettingTest
{

	protected function setUp()
	{
		if ( !isset($this->className) ) {
			$this->className = 'aik099\\SVNBuddy\\Config\\ArrayConfigSetting';
		}

		if ( !isset($this->defaultValue) ) {
			$this->defaultValue = array();
		}

		parent::setUp();
	}

	public function normalizationValueDataProvider()
	{
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
				array('a', 'b'),
				array('a', 'b'),
			),
			'array as scalar' => array(
				'a' . PHP_EOL . 'b',
				array('a', 'b'),
			),
			'array with empty value' => array(
				array('a', '', 'b', ''),
				array('a', 'b'),
			),
			'array with empty value as scalar' => array(
				'a' . PHP_EOL . PHP_EOL . 'b' . PHP_EOL,
				array('a', 'b'),
			),
			'array each element trimmed' => array(
				array(' a ', ' b '),
				array('a', 'b'),
			),
			'array each element trimmed as scalar' => array(
				' a ' . PHP_EOL . ' b ',
				array('a', 'b'),
			),
		);
	}

	public function setValueWithInheritanceDataProvider()
	{
		return array(
			'global, array' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array(array('global_value'), array('default')),
			),
			'working copy, array' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array(array('global_value'), array('default')),
			),
		);
	}

	public function storageDataProvider()
	{
		return array(
			'array into string' => array(array('a', 'b'), 'a' . PHP_EOL . 'b'),
			'array as string' => array('a' . PHP_EOL . 'b', 'a' . PHP_EOL . 'b'),
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
			$ret = array('OK');
		}
		else {
			$ret = array('G_OK');
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
