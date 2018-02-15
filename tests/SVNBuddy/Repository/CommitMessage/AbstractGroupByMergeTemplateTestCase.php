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
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

abstract class AbstractGroupByMergeTemplateTestCase extends AbstractMergeTemplateTestCase
{

	protected function prepareMergeResult()
	{
		$this->connector
			->getRelativePath('svn://repository.com/path/to/project-name/tags/stable')
			->willReturn('/projects/project-name/tags/stable');

		$this->connector
			->getProjectUrl('/projects/project-name/tags/stable')
			->willReturn('/projects/project-name');

		$this->connector
			->getProjectUrl('/projects/project-name/trunk')
			->willReturn('/projects/project-name');

		$this->connector
			->getProjectUrl('/projects/project-name/branches/branch-name')
			->willReturn('/projects/project-name');

		$this->connector
			->getProjectUrl('/projects/another-project-name/tags/stable')
			->willReturn('/projects/another-project-name');

		$this->connector
			->getProjectUrl('/projects/another-project-name/trunk')
			->willReturn('/projects/another-project-name');

		return parent::prepareMergeResult();
	}

}
