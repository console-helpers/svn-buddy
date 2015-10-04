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


use aik099\SVNBuddy\Config\ConfigSetting;
use Prophecy\Argument\Token\TokenInterface;

class ConfigStorageNameToken implements TokenInterface
{

	/**
	 * Pattern.
	 *
	 * @var integer
	 */
	private $_pattern;

	/**
	 * Creates token for matching config setting name used for storage.
	 *
	 * @param string  $name      Config setting name.
	 * @param integer $scope_bit Scope bit.
	 */
	public function __construct($name, $scope_bit)
	{
		if ( $scope_bit === ConfigSetting::SCOPE_WORKING_COPY ) {
			$this->_pattern = '/^path-settings\.(.*)\.' . preg_quote($name, '/') . '$/';
		}
		else {
			$this->_pattern = '/^global-settings\.' . preg_quote($name, '/') . '$/';
		}
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
		return preg_match($this->_pattern, $argument) ? 8 : false;
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
