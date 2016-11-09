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


use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\MergeTemplateFactory;

class MergeTemplateFactoryTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Merge template factory.
	 *
	 * @var MergeTemplateFactory
	 */
	protected $mergeTemplateFactory;

	protected function setUp()
	{
		parent::setUp();

		$this->mergeTemplateFactory = new MergeTemplateFactory();
	}

	public function testAddAndGet()
	{
		$merge_template = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate');
		$merge_template->getName()->willReturn('name');

		$this->mergeTemplateFactory->add($merge_template->reveal());

		$this->assertSame($merge_template->reveal(), $this->mergeTemplateFactory->get('name'));
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The merge template with "name" name is already added.
	 */
	public function testAddError()
	{
		$merge_template = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate');
		$merge_template->getName()->willReturn('name');

		$this->mergeTemplateFactory->add($merge_template->reveal());
		$this->mergeTemplateFactory->add($merge_template->reveal());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The merge template with "name" name is not found.
	 */
	public function testGetError()
	{
		$this->mergeTemplateFactory->get('name');
	}

	public function testGetNames()
	{
		$merge_template = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate');
		$merge_template->getName()->willReturn('name');

		$this->mergeTemplateFactory->add($merge_template->reveal());

		$this->assertSame(array('name'), $this->mergeTemplateFactory->getNames());
	}

}
