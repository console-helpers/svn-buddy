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
	 * Default settings.
	 *
	 * @var array
	 */
	protected static $defaultSettings = array(
		'svn-username' => '',
		'svn-password' => '',
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
	 * @param array  $settings          Settings.
	 * @param string $working_directory Working directory.
	 */
	public function __construct(array $settings, $working_directory)
	{
		foreach ( array_merge(static::$defaultSettings, $settings) as $name => $value ) {
			$this->settings[$name] = str_replace('{base}', $working_directory, $value);
		}
	}

	/**
	 * Creates config class from given config file.
	 *
	 * @param string $filename          Config file path.
	 * @param string $working_directory Working directory.
	 *
	 * @return static
	 */
	public static function createFromFile($filename, $working_directory)
	{
		$filename = str_replace('{base}', $working_directory, $filename);

		if ( !file_exists($filename) ) {
			$options = version_compare(PHP_VERSION, '5.4.0', '>=') ? JSON_PRETTY_PRINT : 0;
			file_put_contents($filename, json_encode(static::$defaultSettings, $options));
		}

		$settings = json_decode(file_get_contents($filename), true);

		return new static($settings, $working_directory);
	}

	/**
	 * Returns config value.
	 *
	 * @param string  $name    Config setting name.
	 * @param boolean $default Default value.
	 *
	 * @return mixed
	 */
	public function get($name, $default = false)
	{
		return isset($this->settings[$name]) ? $this->settings[$name] : $default;
	}

}
