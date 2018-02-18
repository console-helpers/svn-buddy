<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Process;


use Symfony\Component\Process\Process;

interface IProcessFactory
{

	/**
	 * Creates new Symfony process with given arguments.
	 *
	 * @param string       $commandline  The command line to run.
	 * @param integer|null $idle_timeout Idle timeout.
	 *
	 * @return Process
	 */
	public function createProcess(
		$commandline,
		$idle_timeout = null
	);

	/**
	 * Creates new Symfony PHP process with given arguments.
	 *
	 * @param string $command   Command.
	 * @param array  $arguments Arguments.
	 *
	 * @return Process
	 */
	public function createCommandProcess($command, array $arguments = array());

}
