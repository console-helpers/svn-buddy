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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\BugsRevisionLogPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\IRevisionLogPlugin;
use Prophecy\Prophecy\ObjectProphecy;

class BugsRevisionLogPluginTest extends AbstractRevisionLogPluginTestCase
{

	/**
	 * Log message parser.
	 *
	 * @var ObjectProphecy
	 */
	protected $logMessageParser;

	protected function setUp()
	{
		$this->logMessageParser = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Parser\\LogMessageParser');

		parent::setUp();
	}

	public function testGetName()
	{
		$this->assertEquals('bugs', $this->plugin->getName());
	}

	public function testParse()
	{
		$log_message_map = array(
			'JRA-1 - task title' => array('JRA-1'),
			'JRA-2 - task title' => array('JRA-2'),
			'JRA-1 - task title (reverts JRA-3)' => array('JRA-1', 'JRA-3'),
		);

		foreach ( $log_message_map as $log_message => $bugs ) {
			$this->logMessageParser->parse($log_message)->willReturn($bugs)->shouldBeCalled();
		}

		$empty_collected_data = array(
			'revision_bugs' => array(),
			'bug_revisions' => array(),
		);

		$this->assertEquals($empty_collected_data, $this->plugin->getCollectedData());

		$this->plugin->parse($this->getSvnLogFixture());

		$collected_data = array(
			'revision_bugs' => array(
				20128 => array('JRA-1'),
				20127 => array('JRA-2'),
				20125 => array('JRA-1', 'JRA-3'),
			),
			'bug_revisions' => array(
				'JRA-1' => array(20128, 20125),
				'JRA-2' => array(20127),
				'JRA-3' => array(20125),
			),
		);

		$this->assertEquals($collected_data, $this->plugin->getCollectedData());
	}

	public function testFindNoMatch()
	{
		$collected_data = array(
			'revision_bugs' => array(100 => array('JRA-1')),
			'bug_revisions' => array('JRA-1' => array(100)),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEmpty($this->plugin->find(array('JRA-6')), 'No revisions were found.');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array()), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$collected_data = array(
			'revision_bugs' => array(
				100 => array('JRA-1', 'JRA-2'),
			),
			'bug_revisions' => array(
				'JRA-1' => array(100),
				'JRA-2' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100),
			$this->plugin->find(array('JRA-1', 'JRA-2'))
		);
	}

	public function testFindSorting()
	{
		$collected_data = array(
			'revision_bugs' => array(
				100 => array('JRA-1'),
				200 => array('JRA-2'),
			),
			'bug_revisions' => array(
				'JRA-1' => array(100),
				'JRA-2' => array(200),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array('JRA-2', 'JRA-1'))
		);
	}

	public function testGetRevisionDataSuccess()
	{
		$collected_data = array(
			'revision_bugs' => array(
				100 => array('JRA-1', 'JRA-2'),
			),
			'bug_revisions' => array(
				'JRA-1' => array(100),
				'JRA-2' => array(100),
			),
		);

		$this->plugin->setCollectedData($collected_data);

		$this->assertEquals(
			array('JRA-1', 'JRA-2'),
			$this->plugin->getRevisionData(100)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Revision "100" not found by "bugs" plugin.
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
			'revision_bugs' => array(
				200 => array('JRA-2'),
				100 => array('JRA-1'),
			),
			'bug_revisions' => array(
				'JRA-2' => array(200),
				'JRA-1' => array(100),
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
		return new BugsRevisionLogPlugin($this->logMessageParser->reveal());
	}

}
