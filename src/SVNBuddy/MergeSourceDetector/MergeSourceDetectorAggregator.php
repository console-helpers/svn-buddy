<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\MergeSourceDetector;


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
	 */
	public function add(AbstractMergeSourceDetector $merge_source_detector)
	{
		$this->_detectors[] = $merge_source_detector;
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
