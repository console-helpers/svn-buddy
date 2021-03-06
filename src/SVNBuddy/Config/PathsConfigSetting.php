<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Config;


class PathsConfigSetting extends ArrayConfigSetting
{

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
				throw new \InvalidArgumentException('The "' . $path . '" path doesn\'t exist or not a directory.');
			}
		}
	}

}
