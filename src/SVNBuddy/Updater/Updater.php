<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Updater;


use Humbug\SelfUpdate\Updater as BaseUpdater;

class Updater extends BaseUpdater
{

	/**
	 * Detects if new versions are available.
	 *
	 * @return boolean
	 */
	protected function newVersionAvailable()
	{
		$this->newVersion = $this->strategy->getCurrentRemoteVersion($this);
		$this->oldVersion = $this->strategy->getCurrentLocalVersion($this);

		if ( !empty($this->newVersion) && ($this->newVersion !== $this->oldVersion) ) {
			return true;
		}

		return false;
	}

}
