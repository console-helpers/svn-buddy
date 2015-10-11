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


use aik099\SVNBuddy\Config\ConfigSetting;
use aik099\SVNBuddy\Config\PathsConfigSetting;
use Prophecy\Argument;

class PathsConfigSettingTest extends AbstractConfigSettingTest
{

	/**
	 * Temp folder.
	 *
	 * @var string
	 */
	protected $tempFolder = '';

	protected function setUp()
	{
		$this->acceptMultipleDateTypes = false;
		$this->defaultDataType = ConfigSetting::TYPE_ARRAY;

		if ( $this->getName(false) === 'testSetValueWithInheritance' ) {
			$this->defaultValue = $this->getSampleValue('default');
		}
		else {
			$this->defaultValue = array('default');
		}

		parent::setUp();
	}

	public function testValidation()
	{
		$config_setting = $this->createConfigSetting(ConfigSetting::SCOPE_GLOBAL);

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
	 * Creates config setting.
	 *
	 * @param integer $scope_bit   Scope bit.
	 * @param int     $data_type   Data type.
	 * @param boolean $with_editor Connect editor to the setting.
	 *
	 * @return PathsConfigSetting
	 */
	protected function createConfigSetting(
		$scope_bit = null,
		$data_type = null,
		$with_editor = true
	) {
		$config_setting = new PathsConfigSetting('name', $this->defaultValue, $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor->reveal());
		}

		return $config_setting;
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

		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$path = $this->tempFolder . '/OK';
		}
		elseif ( $scope_bit === ConfigSetting::SCOPE_GLOBAL ) {
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

	protected function tearDown()
	{
		parent::tearDown();

		if ( $this->tempFolder && file_exists($this->tempFolder) ) {
			shell_exec('rm -Rf ' . escapeshellarg($this->tempFolder));
		}
	}

}
