<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\RepositoryConnector;


use aik099\SVNBuddy\RepositoryConnector\RevisionListParser;
use Mockery as m;

class RevisionListParserTest extends \PHPUnit_Framework_TestCase
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
	 * @return void
	 */
	protected function setUp()
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

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "r4" revision is invalid.
	 */
	public function testUnknownRevisionSymbol()
	{
		$this->_revisionListParser->expandRanges(array(5, 'r4'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Inverted revision range "5-3" is not implemented.
	 */
	public function testInvertedRange()
	{
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

	public function testRevisionRangeBuilding()
	{
		$this->markTestIncomplete('TODO');

		$expected = array('1:5', '8:9');
		$actual = $this->_revisionListParser->createRanges(array(1, 2, 3, 4, 5, 8, 9));

		$this->assertEquals($expected, $actual);
	}

	public function testSorting()
	{
		$this->markTestIncomplete('TODO');

		$expected = array(1, 2, 3);
		$actual = $this->_revisionListParser->createRanges(array(3, 2, 1));

		$this->assertEquals($expected, $actual);
	}

}
