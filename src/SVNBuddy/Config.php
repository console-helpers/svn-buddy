<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy;


class Config
{

	/**
	 * Filename, where config is stored.
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	protected static $defaultSettings = array(
		'repository-connector' => array(
			'username' => '',
			'password' => '',
		),
	);

	/**
	 * Settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Creates config instance.
	 *
	 * @param string $filename Filename.
	 */
	public function __construct($filename)
	{
		$this->filename = $filename;
		$this->load();
	}

	/**
	 * Returns config value.
	 *
	 * @param string $name    Config setting name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public function get($name, $default = null)
	{
		if ( strpos($name, '.') === false ) {
			return array_key_exists($name, $this->settings) ? $this->settings[$name] : $default;
		}

		$scope_settings = $this->settings;

		foreach ( explode('.', $name) as $name_part ) {
			if ( !array_key_exists($name_part, $scope_settings) ) {
				return $default;
			}

			$scope_settings = $scope_settings[$name_part];
		}

		return $scope_settings;
	}

	/**
	 * Sets config value.
	 *
	 * @param string $name  Config setting name.
	 * @param mixed  $value Config setting value.
	 *
	 * @return void
	 */
	public function set($name, $value)
	{
		if ( strpos($name, '.') === false ) {
			$this->settings[$name] = $value;
			$this->store();

			return;
		}

		$scope_settings =& $this->settings;

		foreach ( explode('.', $name) as $name_part ) {
			if ( !array_key_exists($name_part, $scope_settings) ) {
				$scope_settings[$name_part] = array();
			}

			$scope_settings =& $scope_settings[$name_part];
		}

		if ( $value === null ) {
			eval("unset(\$this->settings['" . str_replace('.', "']['", $name) . "']);");
		}
		else {
			$scope_settings = $value;
		}

		$this->store();
	}

	/**
	 * Loads config contents from disk.
	 *
	 * @return void
	 */
	protected function load()
	{
		if ( file_exists($this->filename) ) {
			$this->settings = json_decode(file_get_contents($this->filename), true);

			return;
		}

		$this->settings = self::$defaultSettings;
		$this->store();
	}

	/**
	 * Stores config contents to the disk.
	 *
	 * @return void
	 */
	protected function store()
	{
		$options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
		file_put_contents($this->filename, json_encode($this->settings, $options));
	}

}
