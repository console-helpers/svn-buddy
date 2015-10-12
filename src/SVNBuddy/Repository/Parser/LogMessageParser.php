<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Repository\Parser;


class LogMessageParser
{

	/**
	 * Regular expression for pre-filtering bugs in log message.
	 *
	 * @var string
	 */
	private $_preFilterRegExp = '';

	/**
	 * Regular expression for matching bugs in log message.
	 *
	 * @var string
	 */
	private $_filterRegExp = '';

	/**
	 * Create instance of log message parser.
	 *
	 * @param string $bugtraq_logregex Regular expression(-s) for bug id finding in log message.
	 */
	public function __construct($bugtraq_logregex)
	{
		$bugtraq_logregex = $this->_replaceMantisToJIRA($bugtraq_logregex);

		$this->_parseBugTraqLogRegex($bugtraq_logregex);
	}

	/**
	 * Replaces Mantis to JIRA.
	 *
	 * @param string $bugtraq_logregex Regular expression(-s) for bug id finding in log message.
	 *
	 * @return string
	 */
	private function _replaceMantisToJIRA($bugtraq_logregex)
	{
		$mantis_regexp = '(?:[Bb]ugs?|[Ii]ssues?|[Rr]eports?|[Ff]ixe?s?|[Rr]esolves?)+\s+(?:#?(?:\d+)[,\.\s]*)+';
		$mantis_regexp .= "\n" . '(\d+)' . "\n";

		if ( $bugtraq_logregex === $mantis_regexp ) {
			return '([A-Z]+\-\d+)';
		}

		return $bugtraq_logregex;
	}

	/**
	 * Parses "bugtraq:logregex" property (if any).
	 *
	 * @param string $bugtraq_logregex Regular expression(-s).
	 *
	 * @return void
	 */
	private function _parseBugTraqLogRegex($bugtraq_logregex)
	{
		$bugtraq_logregex = array_filter(explode(PHP_EOL, $bugtraq_logregex));

		if ( count($bugtraq_logregex) == 2 ) {
			$this->_preFilterRegExp = '/' . $bugtraq_logregex[0] . '/s';
			$this->_filterRegExp = '/' . $bugtraq_logregex[1] . '/s';
		}
		elseif ( count($bugtraq_logregex) == 1 ) {
			$this->_preFilterRegExp = '';
			$this->_filterRegExp = '/' . $bugtraq_logregex[0] . '/s';
		}
		else {
			$this->_preFilterRegExp = '';
			$this->_filterRegExp = '';
		}
	}

	/**
	 * Finds bug IDs in log message.
	 *
	 * @param string $log_message Log message.
	 *
	 * @return array
	 */
	public function parse($log_message)
	{
		if ( !$this->_filterRegExp ) {
			return array();
		}

		if ( $this->_preFilterRegExp ) {
			if ( !preg_match_all($this->_preFilterRegExp, $log_message, $pre_filter_regs) ) {
				return array();
			}

			$ret = array();

			foreach ( $pre_filter_regs[0] as $match ) {
				if ( preg_match_all($this->_filterRegExp, $match, $filter_regs) ) {
					$ret = array_merge($ret, $filter_regs[1]);
				}
			}

			return array_unique($ret);
		}

		if ( preg_match_all($this->_filterRegExp, $log_message, $filter_regs) ) {
			return array_unique($filter_regs[1]);
		}

		return array();
	}

}
