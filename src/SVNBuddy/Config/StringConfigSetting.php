<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace aik099\SVNBuddy\Config;


class StringConfigSetting extends AbstractConfigSetting
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
		return is_string($value) ? trim($value) : $value;
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
		return (string)$value;
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
		if ( !is_string($value) ) {
			throw new \InvalidArgumentException(
				'The "' . $this->getName() . '" config setting value must be a string.'
			);
		}
	}

}
