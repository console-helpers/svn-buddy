<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Repository\RevisionLog;


use aik099\SVNBuddy\Repository\RevisionLog\IRevisionLogPlugin;
use aik099\SVNBuddy\Repository\RevisionLog\SummaryRevisionLogPlugin;

class SummaryRevisionLogPluginTest extends AbstractRevisionLogPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('summary', $this->plugin->getName());
	}

	public function testParse()
	{
		$empty_collected_data = array(
			'revision_summary' => array(),
			'author_revisions' => array(),
		);

		$this->assertEquals($empty_collected_data, $this->plugin->getCollectedData());

		$this->plugin->parse($this->getSvnLogFixture());

		$collected_data = array(
			'revision_summary' => array(
				20128 => array(
					'author' => 'alex',
					'date' => 1444743016,
					'msg' => 'JRA-1 - task title',
				),
				20127 => array(
					'author' => 'erik',
					'date' => 1444741215,
					'msg' => 'JRA-2 - task title',
				),
				20125 => array(
					'author' => 'erik',
					'date' => 1444741215,
					'msg' => 'JRA-1 - task title (reverts JRA-3)',
				),
			),
			'author_revisions' => array(
				'alex' => array(20128),
				'erik' => array(20127, 20125),
			),
		);

		$this->assertEquals($collected_data, $this->plugin->getCollectedData());
	}

	public function testFindNoMatch()
	{
		$collected_data = array(
			'revision_summary' => array(
				100 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			'author_revisions' => array(
				'user' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEmpty($this->plugin->find(array('author:alex')), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$this->markTestIncomplete('Until multi-field search is implemented this can\'t be tested');
	}

	public function testFindSorting()
	{
		$collected_data = array(
			'revision_summary' => array(
				100 => array(
					'author' => 'user1',
					'date' => 0,
					'msg' => 'task title',
				),
				200 => array(
					'author' => 'user2',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			'author_revisions' => array(
				'user1' => array(100),
				'user2' => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array(
				'author:user2',
				'author:user1',
			))
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Each criterion of "summary" plugin must be in "field:value" format.
	 */
	public function testFindMalformedCriterion()
	{
		$collected_data = array(
			'revision_summary' => array(
				100 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			'author_revisions' => array(
				'user' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->plugin->find(array('keyword'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Searching by "field" is not supported by "summary" plugin.
	 */
	public function testFindUnsupportedField()
	{
		$collected_data = array(
			'revision_summary' => array(
				100 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			'author_revisions' => array(
				'user' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->plugin->find(array('field:keyword'));
	}

	public function testGetRevisionDataSuccess()
	{
		$expected = array(
			'author' => 'user',
			'date' => 0,
			'msg' => 'task title',
		);

		$collected_data = array(
			'revision_summary' => array(
				100 => $expected,
			),
			'author_revisions' => array(
				'user' => array(100),
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
	 * @expectedExceptionMessage Revision "100" not found by "summary" plugin.
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
			'revision_summary' => array(
				200 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
				100 => array(
					'author' => 'user',
					'date' => 0,
					'msg' => 'task title',
				),
			),
			'author_revisions' => array(
				'user' => array(200, 100),
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
		return new SummaryRevisionLogPlugin();
	}

}
