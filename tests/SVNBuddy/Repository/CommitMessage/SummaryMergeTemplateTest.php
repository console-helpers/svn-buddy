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
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\SummaryMergeTemplate;

class SummaryMergeTemplateTest extends AbstractMergeTemplateTestCase
{

	public function testGetName()
	{
		$this->assertEquals('summary', $this->mergeTemplate->getName());
	}

	public function testApplyWithMergeChanges()
	{
		$this->connector->getRelativePath('/path/to/working-copy')->willReturn('/path/to/project/tags/stable');
		$this->connector->getLastRevision('/path/to/working-copy')->willReturn(555);

		$this->prepareMergeResult();

		$expected = <<<COMMIT_MSG
Merge of "projects/project-name/trunk@47" to "path/to/project/tags/stable@555".

Merge of "projects/project-name/branches/branch-name@4" to "path/to/project/tags/stable@555".

Merge of "projects/another-project-name/tags/stable@15" to "path/to/project/tags/stable@555".

Merge of "projects/another-project-name/trunk@17" to "path/to/project/tags/stable@555".
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
		return new SummaryMergeTemplate(
			$this->connector->reveal(),
			$this->revisionLogFactory->reveal()
		);
	}

}
