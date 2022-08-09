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
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\GroupByBugMergeTemplate;

class GroupByBugMergeTemplateTest extends AbstractGroupByMergeTemplateTestCase
{

	public function testGetName()
	{
		$this->assertEquals('group_by_bug', $this->mergeTemplate->getName());
	}

	/**
	 * @dataProvider applyWithMergeChangesDataProvider
	 */
	public function testApplyWithMergeChanges($regular_or_reverse)
	{
		list ($revision_log1, $revision_log2, $revision_log3, $revision_log4) = $this->prepareMergeResult($regular_or_reverse);

		if ( $regular_or_reverse ) {
			// Merged revision information.
			$revision_log1->getRevisionsData('bugs', array(18, 33, 47))->willReturn(array(
				18 => array('JRA-100'),
				33 => array('JRA-120'),
				47 => array('JRA-100'),
			));
			$revision_log2->getRevisionsData('bugs', array(4))->willReturn(array(
				4 => array(),
			));
			$revision_log3->getRevisionsData('bugs', array(15))->willReturn(array(
				15 => array(),
			));
			$revision_log4->getRevisionsData('bugs', array(17))->willReturn(array(
				17 => array(),
			));

			$expected = <<<COMMIT_MSG
Merging from Trunk to Stable
* JRA-100 - own-tr1-line1
r18: own-tr1-line2
r47: own-tr3-line2
* JRA-120 - own-tr2-line1
r33: own-tr2-line2

Merge (branch-name > stable): Revisions without Bug IDs:
* r4: own-br1-line1
own-br1-line2

Merge (stable (another-project-name) > stable): Revisions without Bug IDs:
* r15: another-st1-line1
another-st1-line2

Merge (trunk (another-project-name) > stable): Revisions without Bug IDs:
* r17: another-tr1-line1
another-tr1-line2
COMMIT_MSG;
		}
		else {
			// Reverse-merged revision information.
			$revision_log1->getRevisionsData('bugs', array(95, 11))->willReturn(array(
				95 => array('JRA-100'),
				11 => array('JRA-100'),
			));
			$revision_log4->getRevisionsData('bugs', array(112))->willReturn(array(
				112 => array(),
			));

			$expected = <<<COMMIT_MSG
Reverse-merge (trunk > stable): * JRA-100 - own-tr1-line1
r95: own-tr1-line2(r)
r11: own-tr2-line2(r)

Reverse-merge (trunk (another-project-name) > stable): Revisions without Bug IDs:
* r112: another-tr1-line1
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
		return new GroupByBugMergeTemplate(
			$this->connector->reveal(),
			$this->revisionLogFactory->reveal()
		);
	}

}
