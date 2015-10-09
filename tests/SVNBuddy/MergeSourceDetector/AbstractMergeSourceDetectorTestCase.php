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


use aik099\SVNBuddy\MergeSourceDetector\AbstractMergeSourceDetector;

abstract class AbstractMergeSourceDetectorTestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * @dataProvider repositoryUrlDataProvider
	 */
	public function testDetect($repository_url, $result)
	{
		$detector = $this->createDetector();

		$this->assertSame($result, $detector->detect($repository_url));
	}

	public function repositoryUrlDataProvider()
	{
		return array(
			array('', ''),
		);
	}

	public function testWeight()
	{
		$detector = $this->createDetector(100);

		$this->assertEquals(100, $detector->getWeight());
	}

	/**
	 * Creates detector.
	 *
	 * @param integer $weight Weight.
	 *
	 * @return AbstractMergeSourceDetector
	 */
	abstract protected function createDetector($weight = 0);

}
