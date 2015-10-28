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


class ArrayConfigSetting extends AbstractConfigSetting
{

	/**
	 * Normalizes value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function normalizeValue($value)
	{
		if ( !is_array($value) ) {
			$value = strlen($value) ? explode(PHP_EOL, $value) : array();
		}

		return array_filter(array_map('trim', $value));
	}

	/**
	 * Performs value validation.
	 *
	 * @param mixed $value Value.
	 *
	 * @return void
	 */
	protected function validate($value)
	{
		// No validation needed.
	}

	/**
	 * Converts value into scalar for used for storage.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function convertToStorageFormat($value)
	{
		return implode(PHP_EOL, $value);
	}

}
