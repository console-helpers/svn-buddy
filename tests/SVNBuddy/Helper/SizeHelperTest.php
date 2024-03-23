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


use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;
use PHPUnit\Framework\TestCase;

class SizeHelperTest extends TestCase
{

	/**
	 * Container helper
	 *
	 * @var SizeHelper
	 */
	protected $sizeHelper;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->sizeHelper = new SizeHelper();
	}

	/**
	 * @dataProvider formatSizeDataProvider
	 */
	public function testFormatSize($raw_size, $formatted_size)
	{
		$this->assertEquals($formatted_size, $this->sizeHelper->formatSize($raw_size));
	}

	public static function formatSizeDataProvider()
	{
		return array(
			array('1099511627776', '1 TB'),
			array('1073741824', '1 GB'),
			array('1048576', '1 MB'),
			array('1024', '1 KB'),
			array('1', '1 Byte'),
		);
	}

	public function testGetName()
	{
		$this->assertEquals('size', $this->sizeHelper->getName());
	}

}
