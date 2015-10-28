<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\MergeSourceDetector;


use ConsoleHelpers\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use Prophecy\Prophecy\ObjectProphecy;

class MergeSourceDetectorAggregatorTest extends AbstractMergeSourceDetectorTestCase
{

	/**
	 * Detectors.
	 *
	 * @var ObjectProphecy[]
	 */
	protected $detectors;

	protected function setUp()
	{
		parent::setUp();

		$sub_detector1 = $this->prophesize('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector1->getWeight()->willReturn(2)->shouldBeCalled();
		$this->detectors[] = $sub_detector1;

		$sub_detector2 = $this->prophesize('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector2->getWeight()->willReturn(1)->shouldBeCalled();
		$this->detectors[] = $sub_detector2;

		$sub_detector2 = $this->prophesize('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector2->getWeight()->willReturn(3)->shouldBeCalled();
		$this->detectors[] = $sub_detector2;
	}

	/**
	 * @dataProvider repositoryUrlDataProvider
	 */
	public function testDetect($repository_url, $result)
	{
		$this->markTestSkipped('Cross-detector matching tests done separately');
	}

	public function testNoMatchFound()
	{
		$detector = $this->createDetector();

		$this->detectors[0]->detect('A')->willReturn(null);
		$this->detectors[1]->detect('A')->willReturn(null);
		$this->detectors[2]->detect('A')->willReturn(null);

		$this->assertNull($detector->detect('A'));
	}

	public function testFirstMatchByWeightReturned()
	{
		$detector = $this->createDetector();

		$this->detectors[0]->detect('A')->willReturn('A1')->shouldNotBeCalled();
		$this->detectors[1]->detect('A')->willReturn('A2')->shouldNotBeCalled();
		$this->detectors[2]->detect('A')->willReturn('A3')->shouldBeCalled();

		$this->assertEquals('A3', $detector->detect('A'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Another detector with same weight is already added.
	 */
	public function testAddingDetectorWithDuplicateWeight()
	{
		$sub_detector = $this->prophesize('ConsoleHelpers\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector->getWeight()->willReturn(1)->shouldBeCalled();

		$detector = $this->createDetector();
		$detector->add($sub_detector->reveal());
	}

	/**
	 * Creates detector.
	 *
	 * @param integer $weight Weight.
	 *
	 * @return MergeSourceDetectorAggregator
	 */
	protected function createDetector($weight = 0)
	{
		$detector = new MergeSourceDetectorAggregator($weight);

		foreach ( $this->detectors as $sub_detector ) {
			$detector->add($sub_detector->reveal());
		}

		return $detector;
	}

}
