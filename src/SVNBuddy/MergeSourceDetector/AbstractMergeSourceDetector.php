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


abstract class AbstractMergeSourceDetector
{

	/**
	 * Weight.
	 *
	 * @var integer
	 */
	private $_weight;

	/**
	 * Creates detector.
	 *
	 * @param integer $weight Weight.
	 */
	public function __construct($weight)
	{
		$this->_weight = $weight;
	}

	/**
	 * Detects merge source from repository url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return null|string
	 */
	abstract public function detect($repository_url);

	/**
	 * Returns relative detector weight.
	 *
	 * @return integer
	 */
	public function getWeight()
	{
		return $this->_weight;
	}

}
