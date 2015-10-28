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


class IntegerConfigSetting extends AbstractConfigSetting
{

	/**
	 * Converts value into scalar for used for storage.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function convertToStorageFormat($value)
	{
		return (int)$value;
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
		if ( !is_numeric($value) ) {
			throw new \InvalidArgumentException(
				'The "' . $this->getName() . '" config setting value must be an integer.'
			);
		}
	}

}
