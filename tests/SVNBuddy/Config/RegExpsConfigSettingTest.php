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

class RegExpsConfigSettingTest extends ArrayConfigSettingTest
{

	protected function setUp()
	{
		$this->className = 'ConsoleHelpers\\SVNBuddy\\Config\\RegExpsConfigSetting';
		$this->defaultValue = array('default');

		parent::setUp();
	}

	public function testValidation()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->setExpectedException(
			'InvalidArgumentException',
			'The "/wrong-regexp" is not a valid regular expression.'
		);

		$config_setting->setValue(array(
			'/wrong-regexp',
		));
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
			$ret = array('/OK/');
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = array('/G_OK/');
		}
		else {
			$ret = array();

			foreach ( $scope_bit as $index => $value ) {
				$ret[$index] = '/' . $value . '/';
			}
		}

		return $as_stored ? $this->convertToStorage($ret) : $ret;
	}

}
