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

	protected function setUp()
	{
		parent::setUp();

		$this->connector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		$this->revisionLogFactory = $this->prophesize(
			'ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory'
		);

		$this->mergeTemplate = $this->createMergeTemplate();
	}

	abstract public function testGetName();

	public function testApplyWithoutMergeChanges()
	{
		$this->connector->getFreshMergedRevisions('/path/to/working-copy')->willReturn(array());

		$this->assertEmpty($this->mergeTemplate->apply('/path/to/working-copy'));
	}

	abstract public function testApplyWithMergeChanges();

	protected function prepareMergeResult()
	{
		$this->connector->getFreshMergedRevisions('/path/to/working-copy')->willReturn(array(
			'/projects/project-name/trunk' => array('18', '33', '47'),
			'/projects/project-name/branches/branch-name' => array('4'),
			'/projects/another-project-name/tags/stable' => array('15'),
			'/projects/another-project-name/trunk' => array('17'),
		));

		$this->connector
			->getWorkingCopyUrl('/path/to/working-copy')
			->willReturn('svn://repository.com/path/to/project-name/tags/stable');

		$this->connector
			->getRootUrl('svn://repository.com/path/to/project-name/tags/stable')
			->willReturn('svn://repository.com');

		$revision_log1 = $this->getRevisionLog('svn://repository.com/projects/project-name/trunk');
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

		$revision_log2 = $this->getRevisionLog('svn://repository.com/projects/project-name/branches/branch-name');
		$revision_log2->getRevisionsData('summary', array(4))->willReturn(array(
			4 => array(
				'author' => 'user2',
				'date' => 35345444353,
				'msg' => 'own-br1-line1' . PHP_EOL . 'own-br1-line2' . PHP_EOL . PHP_EOL,
			),
		));

		$revision_log3 = $this->getRevisionLog('svn://repository.com/projects/another-project-name/tags/stable');
		$revision_log3->getRevisionsData('summary', array(15))->willReturn(array(
			15 => array(
				'author' => 'user3',
				'date' => 35345444353,
				'msg' => 'another-st1-line1' . PHP_EOL . 'another-st1-line2' . PHP_EOL . PHP_EOL,
			),
		));

		$revision_log4 = $this->getRevisionLog('svn://repository.com/projects/another-project-name/trunk');
		$revision_log4->getRevisionsData('summary', array(17))->willReturn(array(
			17 => array(
				'author' => 'user4',
				'date' => 35345444353,
				'msg' => 'another-tr1-line1' . PHP_EOL . 'another-tr1-line2' . PHP_EOL . PHP_EOL,
			),
		));

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
