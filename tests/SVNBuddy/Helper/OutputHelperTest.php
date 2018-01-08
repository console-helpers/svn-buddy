<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Helper;


use ConsoleHelpers\SVNBuddy\Helper\OutputHelper;
use PHPUnit\Framework\TestCase;

class OutputHelperTest extends TestCase
{

	/**
	 * Container helper
	 *
	 * @var OutputHelper
	 */
	protected $outputHelper;

	protected function setUp()
	{
		parent::setUp();

		$this->outputHelper = new OutputHelper();
	}

	/**
	 * @dataProvider formatArrayWithoutColorDataProvider
	 */
	public function testFormatArrayWithoutColor($items_per_row, $result)
	{
		$this->assertEquals($result, $this->outputHelper->formatArray(array('a', 'b', 'c'), $items_per_row));
	}

	public function formatArrayWithoutColorDataProvider()
	{
		return array(
			array(1, 'a,' . PHP_EOL . 'b,' . PHP_EOL . 'c'),
			array(2, 'a, b,' . PHP_EOL . 'c'),
			array(3, 'a, b, c'),
		);
	}

	/**
	 * @dataProvider formatArrayWithColorDataProvider
	 */
	public function testFormatArrayWithColor($items_per_row, $result)
	{
		$this->assertEquals($result, $this->outputHelper->formatArray(array('a', 'b', 'c'), $items_per_row, 'red'));
	}

	public function formatArrayWithColorDataProvider()
	{
		return array(
			array(1, '<fg=red>a</>,' . PHP_EOL . '<fg=red>b</>,' . PHP_EOL . '<fg=red>c</>'),
			array(2, '<fg=red>a</>, <fg=red>b</>,' . PHP_EOL . '<fg=red>c</>'),
			array(3, '<fg=red>a</>, <fg=red>b</>, <fg=red>c</>'),
		);
	}

	public function testGetName()
	{
		$this->assertEquals('output', $this->outputHelper->getName());
	}

}
