<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Repository\CommitMessageBuilder;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class CommitMessageBuilderTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $connector;

	/**
	 * Revision log factory
	 *
	 * @var ObjectProphecy
	 */
	protected $revisionLogFactory;

	/**
	 * Working copy conflict tracker
	 *
	 * @var ObjectProphecy
	 */
	protected $workingCopyConflictTracker;

	/**
	 * Commit message builder.
	 *
	 * @var CommitMessageBuilder
	 */
	protected $commitMessageBuilder;

	protected function setUp()
	{
		parent::setUp();

		$this->connector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		$revision_list_parser = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser');
		$revision_list_parser->expandRanges(Argument::cetera())->willReturnArgument(0);

		$this->revisionLogFactory = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory'
		);

		$this->workingCopyConflictTracker = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker'
		);

		$this->commitMessageBuilder = new CommitMessageBuilder(
			$this->connector->reveal(),
			$revision_list_parser->reveal(),
			$this->revisionLogFactory->reveal(),
			$this->workingCopyConflictTracker->reveal()
		);
	}

	public function testBuildWithoutChangelist()
	{
		$merge_info = '/projects/project-name/trunk:10,15' . PHP_EOL;
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', 'BASE')->willReturn($merge_info);
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', null)->willReturn($merge_info);
		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array());

		$this->assertEmpty($this->commitMessageBuilder->build('/path/to/working-copy'));
	}

	public function testBuildWithChangelist()
	{
		$merge_info = '/projects/project-name/trunk:10,15' . PHP_EOL;
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', 'BASE')->willReturn($merge_info);
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', null)->willReturn($merge_info);
		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array());

		$this->assertEquals('cl name', $this->commitMessageBuilder->build('/path/to/working-copy', 'cl name'));
	}

	public function testBuildMergeResult()
	{
		$this->prepareMergeResult();

		$expected = <<<COMMIT_MSG
Merging from Trunk to Stable
* r18: a-line1
a-line2
* r33: b-line1
b-line2

Merging from Branch-name to Stable
* r4: c-line1
c-line2
COMMIT_MSG;

		$this->assertEquals($expected, $this->commitMessageBuilder->build('/path/to/working-copy'));
	}

	public function testBuildWithConflicts()
	{
		$merge_info = '/projects/project-name/trunk:10,15' . PHP_EOL;
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', 'BASE')->willReturn($merge_info);
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', null)->willReturn($merge_info);
		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array(
			'sub-folder/file1.txt',
			'file2.ext',
		));

		$expected = <<<COMMIT_MSG

Conflicts:
 * sub-folder/file1.txt
 * file2.ext
COMMIT_MSG;

		$this->assertEquals(
			$expected,
			$this->commitMessageBuilder->build('/path/to/working-copy')
		);
	}

	public function testBuildEverythingPresent()
	{
		$this->prepareMergeResult();

		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array(
			'sub-folder/file1.txt',
			'file2.ext',
		));

		$expected = <<<COMMIT_MSG
cl one
Merging from Trunk to Stable
* r18: a-line1
a-line2
* r33: b-line1
b-line2

Merging from Branch-name to Stable
* r4: c-line1
c-line2

Conflicts:
 * sub-folder/file1.txt
 * file2.ext
COMMIT_MSG;

		$this->assertEquals(
			$expected,
			$this->commitMessageBuilder->build('/path/to/working-copy', 'cl one')
		);
	}

	protected function prepareMergeResult()
	{
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', 'BASE')->willReturn(
			'/projects/project-name/trunk:10,15' . PHP_EOL
		);
		$this->connector->getProperty('svn:mergeinfo', '/path/to/working-copy', null)->willReturn(
			'/projects/project-name/trunk:10,15,18,33' . PHP_EOL .
			'/projects/project-name/branches/branch-name:4' . PHP_EOL
		);

		$this->connector
			->getWorkingCopyUrl('/path/to/working-copy')
			->willReturn('svn://repository.com/path/to/project/tags/stable');

		$this->connector
			->getRootUrl('svn://repository.com/path/to/project/tags/stable')
			->willReturn('svn://repository.com');

		$revision_log1 = $this->getRevisionLog('svn://repository.com/projects/project-name/trunk');
		$revision_log1->getRevisionsData('summary', array(18, 33))->willReturn(array(
			18 => array(
				'author' => 'user1',
				'date' => 3534535353,
				'msg' => 'a-line1' . PHP_EOL . 'a-line2' . PHP_EOL . PHP_EOL,
			),
			33 => array(
				'author' => 'user2',
				'date' => 35345445353,
				'msg' => 'b-line1' . PHP_EOL . 'b-line2' . PHP_EOL . PHP_EOL,
			),
		));

		$revision_log2 = $this->getRevisionLog('svn://repository.com/projects/project-name/branches/branch-name');
		$revision_log2->getRevisionsData('summary', array(4))->willReturn(array(
			4 => array(
				'author' => 'user2',
				'date' => 35345444353,
				'msg' => 'c-line1' . PHP_EOL . 'c-line2' . PHP_EOL . PHP_EOL,
			),
		));
	}

	/**
	 * Returns revision log object for given url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return ObjectProphecy
	 */
	protected function getRevisionLog($repository_url)
	{
		$revision_log = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog');
		$this->revisionLogFactory->getRevisionLog($repository_url)->willReturn($revision_log);

		return $revision_log;
	}

}
