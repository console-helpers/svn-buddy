<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin;


interface IOverwriteAwarePlugin
{

	/**
	 * Sets overwrite mode.
	 *
	 * @param boolean $overwrite_mode Overwrite mode.
	 *
	 * @return void
	 */
	public function setOverwriteMode($overwrite_mode);

	/**
	 * Determines if overwrite mode is enabled.
	 *
	 * @return boolean
	 */
	public function isOverwriteMode();

}
