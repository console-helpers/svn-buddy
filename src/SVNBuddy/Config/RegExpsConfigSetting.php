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


class RegExpsConfigSetting extends ArrayConfigSetting
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

		foreach ( $value as $regexp ) {
			if ( @preg_match($regexp, 'test') === false ) {
				throw new \InvalidArgumentException('The "' . $regexp . '" is not a valid regular expression.');
			}
		}
	}

}
