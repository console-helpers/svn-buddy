<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\Parser;


class LogMessageParserFactory
{

	/**
	 * Log message parsers.
	 *
	 * @var LogMessageParser[]
	 */
	private $_parsers = array();

	/**
	 * Returns log message parser.
	 *
	 * @param string $bugtraq_logregex Regular expression(-s) for bug id finding in log message.
	 *
	 * @return LogMessageParser
	 */
	public function getLogMessageParser($bugtraq_logregex)
	{
		if ( !isset($this->_parsers[$bugtraq_logregex]) ) {
			$this->_parsers[$bugtraq_logregex] = new LogMessageParser($bugtraq_logregex);
		}

		return $this->_parsers[$bugtraq_logregex];
	}

}
