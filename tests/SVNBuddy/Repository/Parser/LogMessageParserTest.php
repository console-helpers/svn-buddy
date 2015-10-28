<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Repository\Parser;


use aik099\SVNBuddy\Repository\Parser\LogMessageParser;

class LogMessageParserTest extends \PHPUnit_Framework_TestCase
{

	public function testEmptyRegExp()
	{
		$parser = new LogMessageParser('');

		$this->assertEmpty($parser->parse('log message'));
	}

	/**
	 * @dataProvider parseWithoutPreFilterDataProvider
	 */
	public function testParseWithoutPreFilter($message, array $bugs)
	{
		$parser = new LogMessageParser('([A-Z]+\-\d+)');

		$this->assertSame($bugs, $parser->parse($message));
	}

	public function parseWithoutPreFilterDataProvider()
	{
		return array(
			'no issues' => array(
				'log message',
				array(),
			),
			'one issue' => array(
				'ABC-1234',
				array('ABC-1234'),
			),
			'several issues' => array(
				'ABC-1234, ABC-5432',
				array('ABC-1234', 'ABC-5432'),
			),
			'several issues and text' => array(
				'Fixes ABC-1234 and ABC-5432 test',
				array('ABC-1234', 'ABC-5432'),
			),
			'several issues multi-line' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and ABC-5432 test',
				array('ABC-1234', 'ABC-5432'),
			),
			'several duplicate issues' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and ABC-1234 test',
				array('ABC-1234'),
			),
		);
	}

	/**
	 * @dataProvider parseWithPreFilterDataProvider
	 */
	public function testParseWithPreFilter($message, array $bugs)
	{
		$parser = new LogMessageParser('[Ff]ixes ([A-Z]+\-\d+)' . PHP_EOL . '([A-Z]+\-\d+)');

		$this->assertSame($bugs, $parser->parse($message));
	}

	public function parseWithPreFilterDataProvider()
	{
		return array(
			'no issues' => array(
				'log message',
				array(),
			),
			'one issue' => array(
				'ABC-1234',
				array(),
			),
			'several issues' => array(
				'ABC-1234, ABC-5432',
				array(),
			),
			'several issues and text' => array(
				'Fixes ABC-1234 and ABC-5432 test',
				array('ABC-1234'),
			),
			'several issues multi-line' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and ABC-5432 test and fixes ABC-3334',
				array('ABC-1234', 'ABC-3334'),
			),
			'several duplicate issues' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and fixes ABC-1234 test',
				array('ABC-1234'),
			),
		);
	}

	public function testMantisIntoJIRAPatching()
	{
		$mantis_regexp = '(?:[Bb]ugs?|[Ii]ssues?|[Rr]eports?|[Ff]ixe?s?|[Rr]esolves?)+\s+(?:#?(?:\d+)[,\.\s]*)+';
		$mantis_regexp .= "\n" . '(\d+)' . "\n";

		$parser = new LogMessageParser($mantis_regexp);

		$this->assertSame(array('JRA-1234'), $parser->parse('JRA-1234'));
	}

}
