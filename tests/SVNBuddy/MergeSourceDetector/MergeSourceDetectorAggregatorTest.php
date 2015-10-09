<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\MergeSourceDetector;


use aik099\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @requires PHP 7.0.0-dev
 */
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

		$sub_detector1 = $this->prophesize('aik099\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector1->getWeight()->willReturn(2);
		$this->detectors[] = $sub_detector1;

		$sub_detector2 = $this->prophesize('aik099\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector2->getWeight()->willReturn(1);
		$this->detectors[] = $sub_detector2;

		$sub_detector2 = $this->prophesize('aik099\\SVNBuddy\\MergeSourceDetector\\AbstractMergeSourceDetector');
		$sub_detector2->getWeight()->willReturn(3);
		$this->detectors[] = $sub_detector2;
	}

	/**
	 * @dataProvider repositoryUrlDataProvider
	 */
	public function testDetect($repository_url, $result)
	{
		$this->markTestSkipped();
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
