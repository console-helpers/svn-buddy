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


class PathsConfigSetting extends ConfigSetting
{

	/**
	 * Creates config setting instance.
	 *
	 * @param string $name    Name.
	 * @param mixed  $default Default value.
	 */
	public function __construct($name, $default)
	{
		parent::__construct($name, self::TYPE_ARRAY, $default);
	}

	/**
	 * Performs value validation.
	 *
	 * @param mixed $value Value.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When validation failed.
	 */
	protected function validate($value)
	{
		parent::validate($value);

		foreach ( $value as $path ) {
			if ( !file_exists($path) || !is_dir($path) ) {
				throw new \InvalidArgumentException('The "' . $path . '" path doesn\'t exit or not a directory.');
			}
		}
	}

}
