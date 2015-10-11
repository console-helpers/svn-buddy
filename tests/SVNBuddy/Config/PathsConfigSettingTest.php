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
use Prophecy\Argument;

class PathsConfigSettingTest extends ArrayConfigSettingTest
{

	/**
	 * Temp folder.
	 *
	 * @var string
	 */
	protected $tempFolder = '';

	protected function setUp()
	{
		$this->className = 'aik099\\SVNBuddy\\Config\\PathsConfigSetting';

		if ( $this->getName(false) === 'testSetValueWithInheritance' ) {
			$this->defaultValue = $this->getSampleValue('default');
		}
		else {
			$this->defaultValue = array('default');
		}

		parent::setUp();
	}

	public function setValueWithInheritanceDataProvider()
	{
		$a_value = $this->getSampleValue('a');
		$b_value = $this->getSampleValue('b');

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

	public function normalizationValueDataProvider()
	{
		$a_value = $this->getSampleValue('a', true);
		$b_value = $this->getSampleValue('b', true);

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

	public function storageDataProvider()
	{
		$a_value = $this->getSampleValue('a', true);
		$b_value = $this->getSampleValue('b', true);

		return array(
			'array into string' => array(array($a_value, $b_value), $a_value . PHP_EOL . $b_value),
			'array as string' => array($a_value . PHP_EOL . $b_value, $a_value . PHP_EOL . $b_value),
		);
	}

	public function testValidation()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->createTempFolder();

		$this->setExpectedException(
			'InvalidArgumentException',
			'The "' . $this->tempFolder . '/non-existing-path" path doesn\'t exist or not a directory.'
		);

		$config_setting->setValue(array(
			$this->tempFolder . '/non-existing-path',
		));
	}

	/**
	 * Creates temp folder.
	 *
	 * @return void
	 */
	protected function createTempFolder()
	{
		if ( $this->tempFolder ) {
			return;
		}

		$temp_file = tempnam(sys_get_temp_dir(), 'sb_');
		unlink($temp_file);
		mkdir($temp_file);

		$this->tempFolder = $temp_file;

		// Don't use "tearDown", because it isn't called for "createTempFolder" within data provider methods.
		register_shutdown_function(function ($temp_file) {
			shell_exec('rm -Rf ' . escapeshellarg($temp_file));
		}, $temp_file);
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
		$this->createTempFolder();

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$path = $this->tempFolder . '/OK';
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$path = $this->tempFolder . '/G_OK';
		}
		else {
			$path = $this->tempFolder . '/' . $scope_bit;
		}

		if ( !file_exists($path) ) {
			mkdir($path);
		}

		$ret = array($path);

		return $as_stored ? $this->convertToStorage($ret) : $ret;
	}

}
