<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Exception;


use ConsoleHelpers\ConsoleKit\Exception\AbstractException;

/**
 * Exception for representing failed Subversion command execution result.
 *
 * @link http://docs.sharpsvn.net/current/html/T_SharpSvn_SvnErrorCode.htm
 * @link https://subversion.apache.org/docs/api/1.8/svn__error__codes_8h_source.html
 * @link https://subversion.apache.org/docs/api/1.8/svn__error__codes_8h.html
 */
class RepositoryCommandException extends AbstractException
{

	const SVN_ERR_WC_UPGRADE_REQUIRED = 155036;

	const SVN_ERR_WC_NOT_WORKING_COPY = 155007;

	const SVN_ERR_FS_NOT_FOUND = 160013;

	const SVN_ERR_BASE = 200000;

	/**
	 * Creates instance of repository command execution exception.
	 *
	 * @param string $command Command.
	 * @param string $stderr  Output from STDERR.
	 */
	public function __construct($command, $stderr)
	{
		$command = \preg_replace('/(--username|--password) ([^\s]+)/', '$1 ********', $command);

		list ($code, $message) = $this->parseErrorOutput($stderr);
		$message = 'Command:' . PHP_EOL . $command . PHP_EOL . 'Error #' . $code . ':' . PHP_EOL . $message;

		parent::__construct($message, $code);
	}

	/**
	 * Parses error output.
	 *
	 * @param string $error_output Error output.
	 *
	 * @return array
	 */
	protected function parseErrorOutput($error_output)
	{
		$error_code = 0;
		$error_message = '';

		$lines = array_filter(explode(PHP_EOL, $error_output));

		foreach ( $lines as $line ) {
			if ( preg_match('/^svn\: E([\d]+)\: (.*)$/', $line, $regs) ) {
				// SVN 1.7+.
				$error_code = (int)$regs[1]; // Typecast, because HHVM's "Exception" class doesn't do this.
				$error_message .= PHP_EOL . $regs[2];
			}
			elseif ( preg_match('/^svn\: (.*)$/', $line, $regs) ) {
				// SVN 1.6-.
				if ( preg_match('/^\'(.*)\' is not a working copy$/', $regs[1]) ) {
					$error_code = self::SVN_ERR_WC_NOT_WORKING_COPY;
				}
				elseif ( preg_match('/^URL \'(.*)\' non-existent in that revision$/', $regs[1]) ) {
					$error_code = self::SVN_ERR_FS_NOT_FOUND;
				}

				$error_message .= PHP_EOL . $regs[1];
			}
			else {
				$error_message .= ' ' . $line;
			}
		}

		return array($error_code, trim($error_message));
	}

}
