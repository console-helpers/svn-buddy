<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\ProphecyToken;


use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;

class ConfigStorageNameToken extends RegExToken
{

	/**
	 * Creates token for matching config setting name used for storage.
	 *
	 * @param string  $name      Config setting name.
	 * @param integer $scope_bit Scope bit.
	 */
	public function __construct($name, $scope_bit)
	{
		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$pattern = '/^path-settings\.(.*)\.' . preg_quote($name, '/') . '$/';
		}
		else {
			$pattern = '/^global-settings\.' . preg_quote($name, '/') . '$/';
		}

		parent::__construct($pattern);
	}

}
