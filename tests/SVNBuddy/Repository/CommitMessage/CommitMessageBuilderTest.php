<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\CommitMessage;


use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\CommitMessageBuilder;
use Prophecy\Prophecy\ObjectProphecy;

class CommitMessageBuilderTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Merge template.
	 *
	 * @var ObjectProphecy
	 */
	protected $mergeTemplate;

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

		$this->mergeTemplate = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate'
		);

		$this->workingCopyConflictTracker = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker'
		);

		$this->commitMessageBuilder = new CommitMessageBuilder(
			$this->workingCopyConflictTracker->reveal()
		);
	}

	public function testBuildWithoutChangelist()
	{
		$this->mergeTemplate->apply('/path/to/working-copy')->willReturn('');
		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array());

		$this->assertEmpty($this->buildCommitMessage());
	}

	public function testBuildWithChangelist()
	{
		$this->mergeTemplate->apply('/path/to/working-copy')->willReturn('');
		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array());

		$this->assertEquals(
			'cl name',
			$this->buildCommitMessage('cl name')
		);
	}

	public function testBuildMergeResult()
	{
		$this->mergeTemplate->apply('/path/to/working-copy')->willReturn('MERGE TEMPLATE');

		$expected = <<<COMMIT_MSG
MERGE TEMPLATE
COMMIT_MSG;

		$this->assertEquals(
			$expected,
			$this->buildCommitMessage()
		);
	}

	public function testBuildWithConflicts()
	{
		$this->mergeTemplate->apply('/path/to/working-copy')->willReturn('');
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
			$this->buildCommitMessage()
		);
	}

	public function testBuildEverythingPresent()
	{
		$this->mergeTemplate->apply('/path/to/working-copy')->willReturn('MERGE TEMPLATE');

		$this->workingCopyConflictTracker->getRecordedConflicts('/path/to/working-copy')->willReturn(array(
			'sub-folder/file1.txt',
			'file2.ext',
		));

		$expected = <<<COMMIT_MSG
cl one
MERGE TEMPLATE

Conflicts:
 * sub-folder/file1.txt
 * file2.ext
COMMIT_MSG;

		$this->assertEquals(
			$expected,
			$this->buildCommitMessage('cl one')
		);
	}

	/**
	 * Builds commit message.
	 *
	 * @param string|null $changelist Changelist.
	 *
	 * @return string
	 */
	protected function buildCommitMessage($changelist = null)
	{
		return $this->commitMessageBuilder->build('/path/to/working-copy', $this->mergeTemplate->reveal(), $changelist);
	}

}
