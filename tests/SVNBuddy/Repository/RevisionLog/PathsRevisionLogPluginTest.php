<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\IRevisionLogPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\PathsRevisionLogPlugin;

class PathsRevisionLogPluginTest extends AbstractRevisionLogPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('paths', $this->plugin->getName());
	}

	public function testParse()
	{
		$empty_collected_data = array(
			'revision_paths' => array(),
			'path_revisions' => array(),
		);

		$this->assertEquals($empty_collected_data, $this->plugin->getCollectedData());

		$this->plugin->parse($this->getSvnLogFixture());

		$collected_data = array(
			'revision_paths' => array(
				20128 => array(
					array(
						'path' => '/projects/project_a/trunk/sub-folder/file.tpl',
						'kind' => 'file',
						'action' => 'M',
					),
					array(
						'path' => '/projects/project_a/trunk/sub-folder',
						'kind' => 'dir',
						'action' => 'M',
					),
				),
				20127 => array(
					array(
						'path' => '/projects/project_a/trunk/another_file.php',
						'kind' => 'file',
						'action' => 'A',
						'unknown-attribute' => 'unknown-value',
					),
				),
				20125 => array(
					array(
						'path' => '/projects/project_a/trunk/another_file.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/projects/project_a/trunk/sub-folder/file.tpl' => array(20128),
				'/projects/project_a/trunk/sub-folder' => array(20128),
				'/projects/project_a/trunk/another_file.php' => array(20127, 20125),
			),
		);

		$this->assertEquals($collected_data, $this->plugin->getCollectedData());
	}

	public function testFindNoMatch()
	{
		$collected_data = array(
			'revision_paths' => array(
				100 => array(
					array(
						'path' => '/folder/sub-folder/file.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/folder/sub-folder/file.php' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEmpty($this->plugin->find(array('/folder/another/sub-folder')), 'No revisions were found.');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array()), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$collected_data = array(
			'revision_paths' => array(
				100 => array(
					array(
						'path' => '/folder/sub-folder/file1.php',
						'kind' => 'file',
						'action' => 'M',
					),
					array(
						'path' => '/folder/sub-folder/file2.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/folder/sub-folder/file1.php' => array(100),
				'/folder/sub-folder/file2.php' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100),
			$this->plugin->find(array(
				'/folder/sub-folder/file1.php',
				'/folder/sub-folder/file2.php',
			))
		);
	}

	public function testFindSorting()
	{
		$collected_data = array(
			'revision_paths' => array(
				100 => array(
					array(
						'path' => '/folder/sub-folder/file1.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
				200 => array(
					array(
						'path' => '/folder/sub-folder/file2.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/folder/sub-folder/file1.php' => array(100),
				'/folder/sub-folder/file2.php' => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array(
				'/folder/sub-folder/file2.php',
				'/folder/sub-folder/file1.php',
			))
		);
	}

	public function testFindAll()
	{
		$collected_data = array(
			'revision_paths' => array(
				100 => array(
					array(
						'path' => '/folder/sub-folder/file1.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
				200 => array(
					array(
						'path' => '/folder/sub-folder/file2.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/folder/sub-folder/file1.php' => array(100),
				'/folder/sub-folder/file2.php' => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array(''))
		);
	}

	public function testGetRevisionDataSuccess()
	{
		$expected = array(
			array(
				'path' => '/folder/sub-folder/file1.php',
				'kind' => 'file',
				'action' => 'M',
			),
			array(
				'path' => '/folder/sub-folder/file2.php',
				'kind' => 'file',
				'action' => 'M',
			),
		);

		$collected_data = array(
			'revision_paths' => array(
				100 => $expected,
			),
			'path_revisions' => array(
				'/folder/sub-folder/file1.php' => array(100),
				'/folder/sub-folder/file2.php' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			$expected,
			$this->plugin->getRevisionData(100)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Revision "100" not found by "paths" plugin.
	 */
	public function testGetRevisionDataFailure()
	{
		$this->plugin->getRevisionData(100);
	}

	public function testGetRevisionsDataSuccess()
	{
		$expected = array(
			array(
				'path' => '/folder/sub-folder/file1.php',
				'kind' => 'file',
				'action' => 'M',
			),
			array(
				'path' => '/folder/sub-folder/file2.php',
				'kind' => 'file',
				'action' => 'M',
			),
		);

		$collected_data = array(
			'revision_paths' => array(
				100 => $expected,
			),
			'path_revisions' => array(
				'/folder/sub-folder/file1.php' => array(100),
				'/folder/sub-folder/file2.php' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100 => $expected),
			$this->plugin->getRevisionsData(array(100))
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Revision(-s) "100" not found by "paths" plugin.
	 */
	public function testGetRevisionsDataFailure()
	{
		$this->plugin->getRevisionsData(array(100));
	}

	public function testGetCacheInvalidator()
	{
		$this->assertInternalType('integer', $this->plugin->getCacheInvalidator());
	}

	public function testGetLastRevisionEmpty()
	{
		$this->assertNull($this->plugin->getLastRevision(), 'No last revision, when no revisions parsed.');
	}

	public function testGetLastRevisionNonEmpty()
	{
		$collected_data = array(
			'revision_paths' => array(
				200 => array(
					array(
						'path' => '/folder/sub-folder/file2.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
				100 => array(
					array(
						'path' => '/folder/sub-folder/file1.php',
						'kind' => 'file',
						'action' => 'M',
					),
				),
			),
			'path_revisions' => array(
				'/folder/sub-folder/file2.php' => array(200),
				'/folder/sub-folder/file1.php' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(100, $this->plugin->getLastRevision());
	}

	/**
	 * Creates plugin.
	 *
	 * @return IRevisionLogPlugin
	 */
	protected function createPlugin()
	{
		return new PathsRevisionLogPlugin();
	}

}
