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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\MergesRevisionLogPlugin;

class MergesRevisionLogPluginTest extends AbstractRevisionLogPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('merges', $this->plugin->getName());
	}

	public function testParse()
	{
		$empty_collected_data = array(
			'merge_revisions' => array(),
			'merged_revisions' => array(),
		);

		$this->assertEquals($empty_collected_data, $this->plugin->getCollectedData());

		$this->plugin->parse($this->getSvnLogFixture());

		$collected_data = array(
			'merge_revisions' => array(
				20128 => array(10100, 10101),
				20125 => array(10101, 10105),
			),
			'merged_revisions' => array(
				10100 => array(20128),
				10101 => array(20128, 20125),
				10105 => array(20125),
			),
		);

		$this->assertEquals($collected_data, $this->plugin->getCollectedData());
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The merge revision 105 not found.
	 */
	public function testFindNoMatch()
	{
		$collected_data = array(
			'merge_revisions' => array(100 => array(50, 60)),
			'merged_revisions' => array(50 => array(100), 60 => array(100)),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->plugin->find(array(105));
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array()), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$collected_data = array(
			'merge_revisions' => array(
				100 => array(50, 60),
				200 => array(60, 65),
			),
			'merged_revisions' => array(
				50 => array(100),
				60 => array(100, 200),
				65 => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array(100, 200))
		);
	}

	public function testFindSorting()
	{
		$collected_data = array(
			'merge_revisions' => array(
				100 => array(50, 60),
				200 => array(60, 65),
			),
			'merged_revisions' => array(
				50 => array(100),
				60 => array(100, 200),
				65 => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array(200, 100))
		);
	}

	public function testFindAllMerges()
	{
		$collected_data = array(
			'merge_revisions' => array(
				100 => array(50, 60),
				200 => array(60, 65),
			),
			'merged_revisions' => array(
				50 => array(100),
				60 => array(100, 200),
				65 => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array('all_merges'))
		);
	}

	public function testFindAllMerged()
	{
		$collected_data = array(
			'merge_revisions' => array(
				100 => array(50, 60),
				200 => array(60, 65),
			),
			'merged_revisions' => array(
				50 => array(100),
				60 => array(100, 200),
				65 => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(50, 60, 65),
			$this->plugin->find(array('all_merged'))
		);
	}

	public function testGetRevisionDataSuccess()
	{
		$collected_data = array(
			'merge_revisions' => array(
				100 => array(50),
				200 => array(50),
			),
			'merged_revisions' => array(
				50 => array(100, 200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->getRevisionData(50)
		);
	}

	public function testGetRevisionDataFailure()
	{
		$this->assertEmpty($this->plugin->getRevisionData(100));
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
			'merge_revisions' => array(
				100 => array(50),
				200 => array(50),
			),
			'merged_revisions' => array(
				50 => array(100, 200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(200, $this->plugin->getLastRevision());
	}

	/**
	 * Creates plugin.
	 *
	 * @return IRevisionLogPlugin
	 */
	protected function createPlugin()
	{
		return new MergesRevisionLogPlugin();
	}

}
