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
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\EmptyMergeTemplate;

class EmptyMergeTemplateTest extends AbstractMergeTemplateTestCase
{

	public function testGetName()
	{
		$this->assertEquals('empty', $this->mergeTemplate->getName());
	}

	/**
	 * @dataProvider applyWithMergeChangesDataProvider
	 */
	public function testApplyWithMergeChanges($regular_or_reverse)
	{
		$this->prepareMergeResult($regular_or_reverse);

		$this->assertEmpty($this->mergeTemplate->apply('/path/to/working-copy'));
	}

	/**
	 * Creates merge template.
	 *
	 * @return AbstractMergeTemplate
	 */
	protected function createMergeTemplate()
	{
		return new EmptyMergeTemplate(
			$this->connector->reveal(),
			$this->revisionLogFactory->reveal()
		);
	}

}
