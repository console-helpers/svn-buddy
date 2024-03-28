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
use ConsoleHelpers\SVNBuddy\Config\PathsConfigSetting;

class PathsConfigSettingTest extends ArrayConfigSettingTest
{

	/**
	 * Temp folder.
	 *
	 * @var string
	 */
	protected static $tempFolder = '';

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->className = PathsConfigSetting::class;
		$this->defaultValue = array('default');

		parent::setupTest();
	}

	public function testValidation()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		self::createTempFolder();

		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage(
			'The "' . self::$tempFolder . '/non-existing-path" path doesn\'t exist or not a directory.'
		);

		$config_setting->setValue(array(
			self::$tempFolder . '/non-existing-path',
		));
	}

	/**
	 * Creates temp folder.
	 *
	 * @return void
	 */
	protected static function createTempFolder()
	{
		if ( self::$tempFolder ) {
			return;
		}

		$temp_file = tempnam(sys_get_temp_dir(), 'sb_');
		unlink($temp_file);
		mkdir($temp_file);

		self::$tempFolder = $temp_file;

		// Don't use "teardownTest", because it isn't called for "createTempFolder" within data provider methods.
		register_shutdown_function(function ($temp_file) {
			shell_exec('rm -Rf ' . escapeshellarg($temp_file));
		}, $temp_file);
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param mixed   $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 */
	protected static function getSampleValue($scope_bit, $as_stored = false)
	{
		self::createTempFolder();

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$ret = array(self::$tempFolder . '/OK');
		}
		elseif ( $scope_bit === AbstractConfigSetting::SCOPE_GLOBAL ) {
			$ret = array(self::$tempFolder . '/G_OK');
		}
		else {
			$ret = array();

			foreach ( $scope_bit as $index => $path ) {
				$ret[$index] = self::$tempFolder . '/' . $path;
			}
		}

		foreach ( $ret as $path ) {
			if ( !file_exists($path) ) {
				mkdir($path);
			}
		}

		return $as_stored ? static::convertToStorage($ret) : $ret;
	}

}
