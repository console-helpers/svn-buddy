<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RepositoryCollectorPlugin;


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;

interface IRepositoryCollectorPlugin extends IPlugin
{

	/**
	 * Returns revision query flags.
	 *
	 * @return array
	 */
	public function getRevisionQueryFlags();

	/**
	 * Parse log entries.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	public function parse(\SimpleXMLElement $log);

}
