<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy;


use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{

	/**
	 * Polyfill for exception setting code.
	 *
	 * @param mixed   $exceptionName    Exception class name.
	 * @param string  $exceptionMessage Exception message.
	 * @param integer $exceptionCode    Exception code.
	 *
	 * @return void
	 * @since  Method available since Release 3.2.0
	 */
	public function setExpectedException($exceptionName, $exceptionMessage = '', $exceptionCode = null)
	{
		if ( \method_exists(\get_parent_class(__CLASS__), 'setExpectedException') ) {
			parent::setExpectedException($exceptionName, $exceptionMessage, $exceptionCode);

			return;
		}

		$this->expectException($exceptionName);

		if ( strlen($exceptionMessage) ) {
			$this->expectExceptionMessage($exceptionMessage);
		}

		if ( isset($exceptionCode) ) {
			$this->expectExceptionCode($exceptionCode);
		}
	}

}
