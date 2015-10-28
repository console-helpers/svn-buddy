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
		$this->className = 'ConsoleHelpers\\SVNBuddy\\Config\\PathsConfigSetting';
		$this->defaultValue = array('default');

		parent::setUp();
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
	 * @param mixed $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 */
	protected function getSampleValue($scope_bit, $as_stored = false)
	{
		$this->createTempFolder();

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$ret = array($this->tempFolder . '/OK');
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = array($this->tempFolder . '/G_OK');
		}
		else {
			$ret = array();

			foreach ( $scope_bit as $index => $path ) {
				$ret[$index] = $this->tempFolder . '/' . $path;
			}
		}

		foreach ( $ret as $path ) {
			if ( !file_exists($path) ) {
				mkdir($path);
			}
		}

		return $as_stored ? $this->convertToStorage($ret) : $ret;
	}

}
