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


use Symfony\Component\Console\Helper\ProgressBar;

interface IDatabaseCollectorPlugin extends IPlugin
{

	/**
	 * Processes data.
	 *
	 * @param integer     $from_revision From revision.
	 * @param integer     $to_revision   To revision.
	 * @param ProgressBar $progress_bar  Progress bar.
	 *
	 * @return void
	 */
	public function process($from_revision, $to_revision, ProgressBar $progress_bar = null);

}
