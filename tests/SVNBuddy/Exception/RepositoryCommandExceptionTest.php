<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Exception;


use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use PHPUnit\Framework\TestCase;

class RepositoryCommandExceptionTest extends TestCase
{

	/**
	 * @dataProvider repositoryErrorDataProvider
	 */
	public function testRepositoryErrorMessageParsing($command, $repository_error, $exception_code, $exception_message)
	{
		$exception = new RepositoryCommandException($command, $repository_error);

		$this->assertEquals($exception_code, $exception->getCode());
		$this->assertEquals($exception_message, $exception->getMessage());
	}

	public static function repositoryErrorDataProvider()
	{
		return array(
			'single-line error' => array(
				'command',
				'svn: error',
				0,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #0:' . PHP_EOL . 'error',
			),
			'multi-line error' => array(
				'command',
				'svn: error1' . PHP_EOL . 'error2' . PHP_EOL . 'svn: error3',
				0,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #0:' . PHP_EOL . 'error1 error2' . PHP_EOL . 'error3',
			),
			'svn 1.6- single-line non-wc error' => array(
				'command',
				"svn: 'some_folder' is not a working copy",
				RepositoryCommandException::SVN_ERR_WC_NOT_WORKING_COPY,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #' . RepositoryCommandException::SVN_ERR_WC_NOT_WORKING_COPY . ':' . PHP_EOL . "'some_folder' is not a working copy",
			),
			'svn 1.6- single-line deleted-path error' => array(
				'command',
				"svn: URL 'svn://domain.com/path/to/folder' non-existent in that revision",
				RepositoryCommandException::SVN_ERR_FS_NOT_FOUND,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #' . RepositoryCommandException::SVN_ERR_FS_NOT_FOUND . ':' . PHP_EOL . "URL 'svn://domain.com/path/to/folder' non-existent in that revision",
			),
			'svn 1.7- single-line error' => array(
				'command',
				'svn: E10: error',
				10,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #10:' . PHP_EOL . 'error',
			),
			'svn 1.7- multi-line error' => array(
				'command',
				'svn: E10: error1' . PHP_EOL . 'error2' . PHP_EOL . 'svn: E10: error3',
				10,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #10:' . PHP_EOL . 'error1 error2' . PHP_EOL . 'error3',
			),
			'single-line svn1.6- error trimming' => array(
				'command',
				'svn:   error  ',
				0,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #0:' . PHP_EOL . 'error',
			),
			'single-line svn1.7+ error trimming' => array(
				'command',
				'svn: E10:  error  ',
				10,
				'Command:' . PHP_EOL . 'command' . PHP_EOL . 'Error #10:' . PHP_EOL . 'error',
			),
			'obfuscate-username' => array(
				'svn --non-interactive --username PLAIN log',
				'svn: error',
				0,
				'Command:' . PHP_EOL . 'svn --non-interactive --username ******** log' . PHP_EOL . 'Error #0:' . PHP_EOL . 'error',
			),
			'obfuscate-password' => array(
				'svn --non-interactive --password PLAIN log',
				'svn: error',
				0,
				'Command:' . PHP_EOL . 'svn --non-interactive --password ******** log' . PHP_EOL . 'Error #0:' . PHP_EOL . 'error',
			),
		);
	}

}
