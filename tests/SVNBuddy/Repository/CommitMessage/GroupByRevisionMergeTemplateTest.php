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


use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\GroupByRevisionMergeTemplate;

class GroupByRevisionMergeTemplateTest extends AbstractMergeTemplateTestCase
{

	public function testGetName()
	{
		$this->assertEquals('group_by_revision', $this->mergeTemplate->getName());
	}

	public function testApplyWithMergeChanges()
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

		$this->assertEquals($expected, $this->mergeTemplate->apply('/path/to/working-copy'));
	}

	/**
	 * Creates merge template.
	 *
	 * @return AbstractMergeTemplate
	 */
	protected function createMergeTemplate()
	{
		return new GroupByRevisionMergeTemplate(
			$this->connector->reveal(),
			$this->revisionLogFactory->reveal()
		);
	}

}
