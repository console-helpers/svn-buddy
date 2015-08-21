<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Process;


use Symfony\Component\Process\Process;

class ProcessFactory implements IProcessFactory
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
	) {
		$process = new Process($commandline);
		$process->setTimeout(null);
		$process->setIdleTimeout($idle_timeout);

		return $process;
	}

}
