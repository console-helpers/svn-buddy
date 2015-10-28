<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace aik099\SVNBuddy\MergeSourceDetector;


class InPortalMergeSourceDetector extends AbstractMergeSourceDetector
{

	/**
	 * Detects merge source from repository url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return null|string
	 */
	public function detect($repository_url)
	{
		// Merging bug fixes branch (e.g. "5.2.x") into feature branch (e.g. "5.3.x") of In-Portal.
		if ( preg_match('#^(.*/branches/[\d]+\.)([\d]+)\.x(/|$)#', $repository_url, $regs) ) {
			return $regs[1] . ($regs[2] - 1) . '.x';
		}

		return null;
	}

}
