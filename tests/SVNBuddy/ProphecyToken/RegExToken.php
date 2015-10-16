<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\ProphecyToken;


use Prophecy\Argument\Token\TokenInterface;

class RegExToken implements TokenInterface
{

	/**
	 * Pattern.
	 *
	 * @var integer
	 */
	private $_pattern;

	/**
	 * Creates token for matching to regular expression.
	 *
	 * @param string $pattern Pattern.
	 */
	public function __construct($pattern)
	{
		$this->_pattern = $pattern;
	}

	/**
	 * Calculates token match score for provided argument.
	 *
	 * @param string $argument Argument.
	 *
	 * @return boolean|integer
	 */
	public function scoreArgument($argument)
	{
		return preg_match($this->_pattern, $argument) ? 6 : false;
	}

	/**
	 * Returns true if this token prevents check of other tokens (is last one).
	 *
	 * @return boolean|integer
	 */
	public function isLast()
	{
		return false;
	}

	/**
	 * Returns string representation for token.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return sprintf('matches("%s")', $this->_pattern);
	}

}
