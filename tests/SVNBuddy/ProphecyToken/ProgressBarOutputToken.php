<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\ProphecyToken;


use Prophecy\Argument\Token\TokenInterface;

class ProgressBarOutputToken implements TokenInterface
{

	/**
	 * Value.
	 *
	 * @var string
	 */
	private $_value;

	/**
	 * Creates token for matching to progress bar output.
	 *
	 * @param string $output Output.
	 */
	public function __construct($output)
	{
		$this->_value = $output;
	}

	/**
	 * @inheritDoc
	 */
	public function scoreArgument($argument)
	{
		return $this->normalize($argument) === $this->normalize($this->_value) ? 6 : false;
	}

	/**
	 * Normalizes text.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	protected function normalize($text)
	{
		$ret = $text;

		// Ignore memory consumption, because it might vary on different PHP versions.
		$ret = preg_replace('#<info>\d+\.\d+ MiB\s+</info>#', '<info>0.0 MiB</info>', $ret);

		// Ignore time consumption, because it might vary on different PHP versions.
		$ret = preg_replace('/([\s]+)?(<\s+)?\d+(\.\d+)? (sec|min)(s)?([\s]+)?/', '5 min', $ret);

		return $ret;
	}

	/**
	 * @inheritDoc
	 */
	public function isLast()
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function __toString()
	{
		return sprintf('progress bar output(%s)', $this->_value);
	}

}
