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


trait TOverwriteAwarePlugin
{

	/**
	 * Overwrite mode.
	 *
	 * @var boolean
	 */
	private $_overwriteMode = false;

	/**
	 * @inheritDoc
	 */
	public function setOverwriteMode($overwrite_mode)
	{
		$this->_overwriteMode = $overwrite_mode;
	}

	/**
	 * @inheritDoc
	 */
	public function isOverwriteMode()
	{
		return $this->_overwriteMode;
	}

}
