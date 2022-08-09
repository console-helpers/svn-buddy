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

class GroupByRevisionMergeTemplateTest extends AbstractGroupByMergeTemplateTestCase
{

	public function testGetName()
	{
		$this->assertEquals('group_by_revision', $this->mergeTemplate->getName());
	}

	/**
	 * @dataProvider applyWithMergeChangesDataProvider
	 */
	public function testApplyWithMergeChanges($regular_or_reverse)
	{
		$this->prepareMergeResult($regular_or_reverse);

		if ( $regular_or_reverse ) {
			$expected = <<<COMMIT_MSG
Merging from Trunk to Stable
* r18: JRA-100 - own-tr1-line1
own-tr1-line2
* r33: JRA-120 - own-tr2-line1
own-tr2-line2
* r47: JRA-100 - own-tr3-line1
own-tr3-line2

Merge (branch-name > stable): * r4: own-br1-line1
own-br1-line2

Merge (stable (another-project-name) > stable): * r15: another-st1-line1
another-st1-line2

Merge (trunk (another-project-name) > stable): * r17: another-tr1-line1
another-tr1-line2
COMMIT_MSG;
		}
		else {
			$expected = <<<COMMIT_MSG
Reverse-merging from Trunk to Stable
* r95: JRA-100 - own-tr1-line1
own-tr1-line2(r)
* r11: JRA-100 - own-tr2-line1
own-tr2-line2(r)

Reverse-merge (trunk (another-project-name) > stable): * r112: another-tr1-line1
another-tr1-line2(r)
COMMIT_MSG;
		}

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
