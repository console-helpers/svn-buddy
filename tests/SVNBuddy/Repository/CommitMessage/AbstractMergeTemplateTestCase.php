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

abstract class AbstractMergeTemplateTestCase extends TestCase
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
	 * Merge template.
	 *
	 * @var AbstractMergeTemplate
	 */
	protected $mergeTemplate;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->connector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		$this->revisionLogFactory = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory'
		);

		$this->mergeTemplate = $this->createMergeTemplate();
	}

	abstract public function testGetName();

	public function testApplyWithoutMerge()
	{
		$this->connector->getMergedRevisionChanges('/path/to/working-copy', true)->willReturn(array());
		$this->connector->getMergedRevisionChanges('/path/to/working-copy', false)->willReturn(array());

		$this->assertEmpty($this->mergeTemplate->apply('/path/to/working-copy'));
	}

	public function testApplyWithoutMergeChanges()
	{
		$this->connector->getMergedRevisionChanges('/path/to/working-copy', true)->willReturn(array(
			'/projects/project-name/trunk' => array(),
		));
		$this->connector->getMergedRevisionChanges('/path/to/working-copy', false)->willReturn(array(
			'/projects/project-name/trunk' => array(),
		));

		$this->connector
			->getWorkingCopyUrl('/path/to/working-copy')
			->willReturn('svn://repository.com/path/to/project-name/tags/stable');

		$this->connector
			->getRelativePath('svn://repository.com/path/to/project-name/tags/stable')
			->willReturn('/projects/project-name/tags/stable');

		$this->connector
			->getRootUrl('svn://repository.com/path/to/project-name/tags/stable')
			->willReturn('svn://repository.com');

		$this->assertEmpty($this->mergeTemplate->apply('/path/to/working-copy'));
	}

	abstract public function testApplyWithMergeChanges($regular_or_reverse);

	public function applyWithMergeChangesDataProvider()
	{
		return array(
			'merge' => array(true),
			'reverse-merge' => array(false),
		);
	}

	protected function prepareMergeResult($regular_or_reverse)
	{
		$revision_log1 = $this->getRevisionLog('svn://repository.com/projects/project-name/trunk');
		$revision_log2 = $this->getRevisionLog('svn://repository.com/projects/project-name/branches/branch-name');
		$revision_log3 = $this->getRevisionLog('svn://repository.com/projects/another-project-name/tags/stable');
		$revision_log4 = $this->getRevisionLog('svn://repository.com/projects/another-project-name/trunk');

		if ( $regular_or_reverse ) {
			// Merged revision information.
			$this->connector->getMergedRevisionChanges('/path/to/working-copy', true)->willReturn(array(
				'/projects/project-name/trunk' => array('18', '33', '47'),
				'/projects/project-name/branches/branch-name' => array('4'),
				'/projects/another-project-name/tags/stable' => array('15'),
				'/projects/another-project-name/trunk' => array('17'),
			));

			$revision_log1->getRevisionsData('summary', array(18, 33, 47))->willReturn(array(
				18 => array(
					'author' => 'user1',
					'date' => 3534535353,
					'msg' => 'JRA-100 - own-tr1-line1' . PHP_EOL . 'own-tr1-line2' . PHP_EOL . PHP_EOL,
				),
				33 => array(
					'author' => 'user2',
					'date' => 35345445353,
					'msg' => 'JRA-120 - own-tr2-line1' . PHP_EOL . 'own-tr2-line2' . PHP_EOL . PHP_EOL,
				),
				47 => array(
					'author' => 'user3',
					'date' => 35345445353,
					'msg' => 'JRA-100 - own-tr3-line1' . PHP_EOL . 'own-tr3-line2' . PHP_EOL . PHP_EOL,
				),
			));

			$revision_log2->getRevisionsData('summary', array(4))->willReturn(array(
				4 => array(
					'author' => 'user2',
					'date' => 35345444353,
					'msg' => 'own-br1-line1' . PHP_EOL . 'own-br1-line2' . PHP_EOL . PHP_EOL,
				),
			));

			$revision_log3->getRevisionsData('summary', array(15))->willReturn(array(
				15 => array(
					'author' => 'user3',
					'date' => 35345444353,
					'msg' => 'another-st1-line1' . PHP_EOL . 'another-st1-line2' . PHP_EOL . PHP_EOL,
				),
			));

			$revision_log4->getRevisionsData('summary', array(17))->willReturn(array(
				17 => array(
					'author' => 'user4',
					'date' => 35345444353,
					'msg' => 'another-tr1-line1' . PHP_EOL . 'another-tr1-line2' . PHP_EOL . PHP_EOL,
				),
			));

			// Reverse-merged revision information.
			$this->connector->getMergedRevisionChanges('/path/to/working-copy', false)->willReturn(array(
				'/projects/project-name/trunk' => array(),
			));
		}
		else {
			// Merged revision information.
			$this->connector->getMergedRevisionChanges('/path/to/working-copy', true)->willReturn(array(
				'/projects/project-name/trunk' => array(),
			));

			// Reverse-merged revision information.
			$this->connector->getMergedRevisionChanges('/path/to/working-copy', false)->willReturn(array(
				'/projects/project-name/trunk' => array('95', '11'),
				'/projects/another-project-name/trunk' => array('112'),
			));

			$revision_log1->getRevisionsData('summary', array(95, 11))->willReturn(array(
				95 => array(
					'author' => 'user5',
					'date' => 3534535353,
					'msg' => 'JRA-100 - own-tr1-line1' . PHP_EOL . 'own-tr1-line2(r)' . PHP_EOL . PHP_EOL,
				),
				11 => array(
					'author' => 'user6',
					'date' => 35345445353,
					'msg' => 'JRA-100 - own-tr2-line1' . PHP_EOL . 'own-tr2-line2(r)' . PHP_EOL . PHP_EOL,
				),
			));

			$revision_log4->getRevisionsData('summary', array(112))->willReturn(array(
				112 => array(
					'author' => 'user7',
					'date' => 35345444353,
					'msg' => 'another-tr1-line1' . PHP_EOL . 'another-tr1-line2(r)' . PHP_EOL . PHP_EOL,
				),
			));
		}

		$this->connector
			->getWorkingCopyUrl('/path/to/working-copy')
			->willReturn('svn://repository.com/path/to/project-name/tags/stable');

		$this->connector
			->getRootUrl('svn://repository.com/path/to/project-name/tags/stable')
			->willReturn('svn://repository.com');

		return array($revision_log1, $revision_log2, $revision_log3, $revision_log4);
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

	/**
	 * Creates merge template.
	 *
	 * @return AbstractMergeTemplate
	 */
	abstract protected function createMergeTemplate();

}
