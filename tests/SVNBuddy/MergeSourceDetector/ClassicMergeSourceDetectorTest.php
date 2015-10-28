<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\MergeSourceDetector;


use ConsoleHelpers\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;

class ClassicMergeSourceDetectorTest extends AbstractMergeSourceDetectorTestCase
{

	public function repositoryUrlDataProvider()
	{
		return array(
			'repo root folder' => array('svn://localhost', null),

			'trunk root folder' => array('svn://localhost/trunk', null),
			'trunk sub-folder' => array('svn://localhost/trunk/folder', null),

			'branches root folder' => array('svn://localhost/branches', null),
			'branch root folder' => array('svn://localhost/branches/branch_name', null),
			'branch sub-folder' => array('svn://localhost/branches/branch_name/folder', null),

			'tags root folder' => array('svn://localhost/tags', null),
			'tag root folder' => array('svn://localhost/tags/tag_name', 'svn://localhost/trunk'),
			'tag sub-folder' => array('svn://localhost/tags/tag_name/folder', 'svn://localhost/trunk'),
		);
	}

	/**
	 * Creates detector.
	 *
	 * @param integer $weight Weight.
	 *
	 * @return ClassicMergeSourceDetector
	 */
	protected function createDetector($weight = 0)
	{
		return new ClassicMergeSourceDetector($weight);
	}

}
