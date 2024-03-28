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
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class MergeTemplateFactoryTest extends AbstractTestCase
{

	use ExpectException;

	/**
	 * Merge template factory.
	 *
	 * @var MergeTemplateFactory
	 */
	protected $mergeTemplateFactory;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->mergeTemplateFactory = new MergeTemplateFactory();
	}

	public function testAddAndGet()
	{
		$merge_template = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate');
		$merge_template->getName()->willReturn('name');

		$this->mergeTemplateFactory->add($merge_template->reveal());

		$this->assertSame($merge_template->reveal(), $this->mergeTemplateFactory->get('name'));
	}

	public function testAddError()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The merge template with "name" name is already added.');

		$merge_template = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate');
		$merge_template->getName()->willReturn('name');

		$this->mergeTemplateFactory->add($merge_template->reveal());
		$this->mergeTemplateFactory->add($merge_template->reveal());
	}

	public function testGetError()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The merge template with "name" name is not found.');

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
