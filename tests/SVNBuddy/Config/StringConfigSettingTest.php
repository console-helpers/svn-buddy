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

	public function normalizationValueDataProvider()
	{
		return array(
			'empty string' => array(
				'',
				'',
			),
			'one line string' => array(
				'a',
				'a',
			),
			'one line string trimmed' => array(
				' a ',
				'a',
			),
			'multi-line string' => array(
				'a' . PHP_EOL . 'b',
				'a' . PHP_EOL . 'b',
			),
			'multi-line string trimmed' => array(
				' a' . PHP_EOL . 'b ',
				'a' . PHP_EOL . 'b',
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

	public function setValueWithInheritanceDataProvider()
	{
		return array(
			'global, string' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array('global_value', 'default'),
			),
			'working copy, string' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array('global_value', 'default'),
			),
		);
	}

	public function storageDataProvider()
	{
		return array(
			'string' => array('a', 'a'),
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
			$ret = 'OK';
		}
		else {
			$ret = 'G_OK';
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
