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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RefsRevisionLogPlugin;

class RefsRevisionLogPluginTest extends AbstractRevisionLogPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('refs', $this->plugin->getName());
	}

	public function testParse()
	{
		$empty_collected_data = array(
			'revision_refs' => array(),
			'ref_revisions' => array(),
		);

		$this->assertEquals($empty_collected_data, $this->plugin->getCollectedData());

		$this->plugin->parse($this->getSvnLogFixture());

		$collected_data = array(
			'revision_refs' => array(
				20128 => array(
					'trunk',
				),
				20127 => array(
					'branches/branch-name',
				),
				20125 => array(
					'branches/branch-name',
				),
				20124 => array(
					'tags/tag-name',
				),
				20122 => array(
					'releases/release-name',
				),
			),
			'ref_revisions' => array(
				'trunk' => array(20128),
				'branches/branch-name' => array(20127, 20125),
				'tags/tag-name' => array(20124),
				'releases/release-name' => array(20122),
			),
		);

		$this->assertEquals($collected_data, $this->plugin->getCollectedData());
	}

	public function testFindNoMatch()
	{
		$collected_data = array(
			'revision_refs' => array(
				100 => array(
					array(
						'branches/branch-name',
						'tags/tag-name',
					),
				),
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(100),
				'tags/tag-name' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEmpty($this->plugin->find(array('branches/new-branch')), 'No revisions were found.');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array()), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$collected_data = array(
			'revision_refs' => array(
				100 => array(
					array(
						'branches/branch-name',
						'tags/tag-name',
					),
				),
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(100),
				'tags/tag-name' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100),
			$this->plugin->find(array(
				'branches/branch-name',
				'tags/tag-name',
			))
		);
	}

	public function testFindAllRefs()
	{
		$collected_data = array(
			'revision_refs' => array(
				100 => array(
					array(
						'branches/branch-name',
						'tags/tag-name',
					),
				),
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(100),
				'tags/tag-name' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(
				'branches/branch-name',
				'tags/tag-name',
			),
			$this->plugin->find(array('all_refs'))
		);
	}

	public function testFindSorting()
	{
		$collected_data = array(
			'revision_refs' => array(
				100 => array(
					array(
						'branches/branch-name',
					),
				),
				200 => array(
					'tags/tag-name',
				),
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(100),
				'tags/tag-name' => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array(
				'tags/tag-name',
				'branches/branch-name',
			))
		);
	}

	public function testGetRevisionDataSuccess()
	{
		$expected = array(
			array(
				'branches/branch-name',
				'tags/tag-name',
			),
		);

		$collected_data = array(
			'revision_refs' => array(
				100 => $expected,
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(100),
				'tags/tag-name' => array(100),
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
	 * @expectedExceptionMessage Revision "100" not found by "refs" plugin.
	 */
	public function testGetRevisionDataFailure()
	{
		$this->plugin->getRevisionData(100);
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
			'revision_refs' => array(
				200 => array(
					array(
						'branches/branch-name',
					),
				),
				100 => array(
					'tags/tag-name',
				),
			),
			'ref_revisions' => array(
				'branches/branch-name' => array(200),
				'tags/tag-name' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(100, $this->plugin->getLastRevision());
	}

	/**
	 * Expects query to "svn log".
	 *
	 * @return \SimpleXMLElement
	 */
	protected function getSvnLogFixture()
	{
		$svn_log_output = <<<XML
<?xml version="1.0"?>
<log>
   <logentry revision="20128">
	  <author>alex</author>
	  <date>2015-10-13T13:30:16.473960Z</date>
	  <paths>
		 <path action="M" kind="file">/projects/project_a/trunk/sub-folder/file.tpl</path>
		 <path action="M" kind="dir">/projects/project_a/trunk/sub-folder</path>
	  </paths>
	  <msg>JRA-1 - task title</msg>
	  <logentry revision="10100"></logentry>
	  <logentry revision="10101"></logentry>
   </logentry>
   <logentry revision="20127">
	  <author>erik</author>
	  <date>2015-10-13T13:00:15.434252Z</date>
	  <paths>
		 <path action="A" kind="file" unknown-attribute="unknown-value">/projects/project_a/branches/branch-name/another_file.php</path>
	  </paths>
	  <msg>JRA-2 - task title</msg>
   </logentry>
   <logentry revision="20125">
	  <author>erik</author>
	  <date>2015-10-13T13:00:15.434252Z</date>
	  <paths>
		 <path action="M" kind="file">/projects/project_a/branches/branch-name/another_file.php</path>
	  </paths>
	  <msg>JRA-1 - task title (reverts JRA-3)</msg>
	  <logentry revision="10101"></logentry>
	  <logentry revision="10105"></logentry>
   </logentry>
   <logentry revision="20124">
	  <author>erik</author>
	  <date>2015-10-13T13:00:15.434252Z</date>
	  <paths>
		 <path action="M" kind="file">/projects/project_a/tags/tag-name/another_file.php</path>
	  </paths>
	  <msg>JRA-1 - task title (reverts JRA-3)</msg>
	  <logentry revision="10101"></logentry>
	  <logentry revision="10105"></logentry>
   </logentry>
   <logentry revision="20123">
	  <author>erik</author>
	  <date>2015-10-13T13:00:15.434252Z</date>
	  <paths>
		 <path action="M" kind="file">/projects/project_a/unknowns/unknown-name/another_file.php</path>
	  </paths>
	  <msg>JRA-1 - task title (reverts JRA-3)</msg>
	  <logentry revision="10101"></logentry>
	  <logentry revision="10105"></logentry>
   </logentry>
   <logentry revision="20122">
	  <author>erik</author>
	  <date>2015-10-13T13:00:15.434252Z</date>
	  <paths>
		 <path action="M" kind="file">/projects/project_a/releases/release-name/another_file.php</path>
	  </paths>
	  <msg>JRA-1 - task title (reverts JRA-3)</msg>
	  <logentry revision="10101"></logentry>
	  <logentry revision="10105"></logentry>
   </logentry>
</log>
XML;

		return new \SimpleXMLElement($svn_log_output);
	}

	/**
	 * Creates plugin.
	 *
	 * @return IRevisionLogPlugin
	 */
	protected function createPlugin()
	{
		return new RefsRevisionLogPlugin();
	}

}
