<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\Parser;


use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use PHPUnit\Framework\TestCase;

class LogMessageParserFactoryTest extends TestCase
{

	/**
	 * Log message parser factory
	 *
	 * @var LogMessageParserFactory
	 */
	protected $logMessageParserFactory;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->logMessageParserFactory = new LogMessageParserFactory();
	}

	public function testParserIsReturned()
	{
		$this->assertInstanceOf(
			'ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser',
			$this->logMessageParserFactory->getLogMessageParser('.*')
		);
	}

	public function testDifferentParserForDifferentInput()
	{
		$parser1 = $this->logMessageParserFactory->getLogMessageParser('.*');
		$parser2 = $this->logMessageParserFactory->getLogMessageParser('TASK .*');

		$this->assertNotSame($parser1, $parser2);
	}

	public function testSameParserForSameInput()
	{
		$parser1 = $this->logMessageParserFactory->getLogMessageParser('.*');
		$parser2 = $this->logMessageParserFactory->getLogMessageParser('.*');

		$this->assertSame($parser1, $parser2);
	}

}
