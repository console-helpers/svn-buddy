<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Config;


class ConfigEditor
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
		'repository-connector.username' => '',
		'repository-connector.password' => '',
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
		if ( substr($name, -1) == '.' ) {
			$ret = array();

			foreach ( $this->settings as $setting_name => $setting_value ) {
				if ( preg_match('/^' . preg_quote($name, '/') . '/', $setting_name) ) {
					$ret[$setting_name] = $setting_value;
				}
			}

			return $ret;
		}

		return array_key_exists($name, $this->settings) ? $this->settings[$name] : $default;
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
		if ( $value === null ) {
			unset($this->settings[$name]);
		}
		else {
			$this->settings[$name] = $value;
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
