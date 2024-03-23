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


use ConsoleHelpers\SVNBuddy\MergeSourceDetector\InPortalMergeSourceDetector;

class InPortalMergeSourceDetectorTest extends AbstractMergeSourceDetectorTestCase
{

	public static function repositoryUrlDataProvider()
	{
		return array(
			'repo root folder' => array('svn://localhost', null),

			'trunk root folder' => array('svn://localhost/trunk', null),
			'trunk sub-folder' => array('svn://localhost/trunk/folder', null),

			'branches root folder' => array('svn://localhost/branches', null),
			'branch root folder' => array('svn://localhost/branches/branch_name', null),
			'branch sub-folder' => array('svn://localhost/branches/branch_name/folder', null),
			'version branch root folder' => array('svn://localhost/branches/1.1.1', null),
			'version branch sub-folder' => array('svn://localhost/branches/1.1.1/folder', null),
			'x-version branch root folder' => array('svn://localhost/branches/1.1.x', 'svn://localhost/branches/1.0.x'),
			'first x-version branch root folder' => array('svn://localhost/branches/1.0.x', null),
			'x-version branch sub-folder' => array('svn://localhost/branches/1.1.x/folder', 'svn://localhost/branches/1.0.x'),
			'first x-version branch sub-folder' => array('svn://localhost/branches/1.0.x/folder', null),

			'tags root folder' => array('svn://localhost/tags', null),
			'tag root folder' => array('svn://localhost/tags/tag_name', null),
			'tag sub-folder' => array('svn://localhost/tags/tag_name/folder', null),
			'version tag root folder' => array('svn://localhost/tags/1.1.1', null),
			'version tag sub-folder' => array('svn://localhost/tags/1.1.1/folder', null),
			'x-version tag root folder' => array('svn://localhost/tags/1.1.x', null),
			'first x-version tag root folder' => array('svn://localhost/tags/1.0.x', null),
			'x-version tag sub-folder' => array('svn://localhost/tags/1.1.x/folder', null),
			'first x-version tag sub-folder' => array('svn://localhost/tags/1.0.x/folder', null),
		);
	}

	/**
	 * Creates detector.
	 *
	 * @param integer $weight Weight.
	 *
	 * @return InPortalMergeSourceDetector
	 */
	protected function createDetector($weight = 0)
	{
		return new InPortalMergeSourceDetector($weight);
	}

}
