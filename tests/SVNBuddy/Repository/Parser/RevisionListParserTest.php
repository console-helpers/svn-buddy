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


use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class RevisionListParserTest extends AbstractTestCase
{

	/**
	 * Repository connector.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

	/**
	 * Prepares fixture.
	 *
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->_revisionListParser = new RevisionListParser();
	}

	public function testSingleRevision()
	{
		$expected = array(1);
		$actual = $this->_revisionListParser->expandRanges(array(1));

		$this->assertEquals($expected, $actual);
	}

	public function testMultipleRevisions()
	{
		$expected = array(1, 2, 3);
		$actual = $this->_revisionListParser->expandRanges(array(1, 2, 3));

		$this->assertEquals($expected, $actual);
	}

	public function testRevisionRegularRange()
	{
		$expected = array(1, 2, 3, 4);
		$actual = $this->_revisionListParser->expandRanges(array('1-4'));

		$this->assertEquals($expected, $actual);
	}

	public function testOneRevisionRange()
	{
		$expected = array(2);
		$actual = $this->_revisionListParser->expandRanges(array('2-2'));

		$this->assertEquals($expected, $actual);
	}

	public function testUnknownRevisionSymbol()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('The "r4" revision is invalid.');

		$this->_revisionListParser->expandRanges(array(5, 'r4'));
	}

	public function testInvertedRange()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('Inverted revision range "5-3" is not implemented.');

		$this->_revisionListParser->expandRanges(array('5-3'));
	}

	public function testDuplicates()
	{
		$expected = array(1, 5, 7);
		$actual = $this->_revisionListParser->expandRanges(array(1, 5, 1, 7));

		$this->assertEquals($expected, $actual);
	}

	public function testRangeMerging()
	{
		$expected = array(1, 2, 3, 4, 5, 6, 7, 8);
		$actual = $this->_revisionListParser->expandRanges(array('1-5', 3, '4-8'));

		$this->assertEquals($expected, $actual);
	}

	public function testMultiDigitRevisions()
	{
		$expected = array(18, 10, 11, 12);
		$actual = $this->_revisionListParser->expandRanges(array(18, '10-12'));

		$this->assertEquals($expected, $actual);
	}

}
