<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\MergeSourceDetector;


class MergeSourceDetectorAggregator extends AbstractMergeSourceDetector
{

	/**
	 * Merge sources.
	 *
	 * @var AbstractMergeSourceDetector[]
	 */
	private $_detectors = array();

	/**
	 * Adds merge source detector.
	 *
	 * @param AbstractMergeSourceDetector $merge_source_detector Merge source detector.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When another detector with same weight was added.
	 */
	public function add(AbstractMergeSourceDetector $merge_source_detector)
	{
		$weight = $merge_source_detector->getWeight();

		if ( array_key_exists($weight, $this->_detectors) ) {
			throw new \InvalidArgumentException('Another detector with same weight is already added.');
		}

		$this->_detectors[$weight] = $merge_source_detector;
		krsort($this->_detectors, SORT_NUMERIC);
	}

	/**
	 * Detects merge source from repository url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return null|string
	 */
	public function detect($repository_url)
	{
		foreach ( $this->_detectors as $detector ) {
			$result = $detector->detect($repository_url);

			if ( isset($result) ) {
				return $result;
			}
		}

		return null;
	}

}
