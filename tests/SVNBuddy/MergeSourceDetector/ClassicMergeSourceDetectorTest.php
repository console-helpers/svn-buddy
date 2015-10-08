<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\MergeSourceDetector;


use aik099\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;

class ClassicMergeSourceDetectorTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @dataProvider repositoryUrlDataProvider
	 */
	public function testDetect($repository_url, $result)
	{
		$merge_source_detector = new ClassicMergeSourceDetector();

		$this->assertSame($result, $merge_source_detector->detect($repository_url));
	}

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

}
