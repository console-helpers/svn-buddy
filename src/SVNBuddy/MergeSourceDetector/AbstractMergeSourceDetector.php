<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\MergeSourceDetector;


abstract class AbstractMergeSourceDetector
{

	/**
	 * Detects merge source from repository url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return null|string
	 */
	abstract public function detect($repository_url);

}
