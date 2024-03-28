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


use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class LogMessageParserTest extends AbstractTestCase
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

	public static function parseWithoutPreFilterDataProvider()
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

	public static function parseWithPreFilterDataProvider()
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

	/**
	 * @dataProvider parseMultipleIssueTrackersWithPreFilterDataProvider
	 */
	public function testParseMultipleIssueTrackersWithPreFilter($message, array $bugs)
	{
		$parser = new LogMessageParser('([A-Z]+\-\d+|#\d+\s+)' . PHP_EOL . '([A-Z]+\-\d+|\d+)');

		$this->assertSame($bugs, $parser->parse($message));
	}

	public static function parseMultipleIssueTrackersWithPreFilterDataProvider()
	{
		return array(
			'both trackers: no issues' => array(
				'log message',
				array(),
			),

			// Tracker 1 fixtures.
			'tracker 1: one issue' => array(
				'ABC-1234',
				array('ABC-1234'),
			),
			'tracker 1: several issues' => array(
				'ABC-1234, ABC-5432',
				array('ABC-1234', 'ABC-5432'),
			),
			'tracker 1: several issues and text' => array(
				'Fixes ABC-1234 and ABC-5432 test',
				array('ABC-1234', 'ABC-5432'),
			),
			'tracker 1: several issues multi-line' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and ABC-5432 test',
				array('ABC-1234', 'ABC-5432'),
			),
			'tracker 1: several duplicate issues' => array(
				'Fixes ABC-1234 ' . PHP_EOL . 'and ABC-1234 test',
				array('ABC-1234'),
			),

			// Tracker 2 fixtures.
			'tracker 2: no issues' => array(
				'1234',
				array(),
			),
			'tracker 2: one issue' => array(
				'#1234 ',
				array('1234'),
			),
			'tracker 2: several issues' => array(
				'#1234 #5432 ',
				array('1234', '5432'),
			),
			'tracker 2: several issues and text' => array(
				'Fixes #1234 and #5432 test',
				array('1234', '5432'),
			),
			'tracker 2: several issues multi-line' => array(
				'Fixes #1234 ' . PHP_EOL . 'and #5432 test',
				array('1234', '5432'),
			),
			'tracker 2: several duplicate issues' => array(
				'Fixes #1234 ' . PHP_EOL . 'and #1234 test',
				array('1234'),
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
