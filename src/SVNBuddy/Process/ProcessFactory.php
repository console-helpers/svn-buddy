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


use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

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

	/**
	 * Creates new Symfony PHP process with given arguments.
	 *
	 * @param string $command   Command.
	 * @param array  $arguments Arguments.
	 *
	 * @return Process
	 * @throws \RuntimeException When PHP executable can't be found.
	 */
	public function createCommandProcess($command, array $arguments = array())
	{
		$php_executable_finder = new PhpExecutableFinder();
		$php_executable = $php_executable_finder->find();

		if ( !$php_executable ) {
			throw new \RuntimeException('The PHP executable cannot be found.');
		}

		array_unshift($arguments, $php_executable, $_SERVER['argv'][0], $command);

		return ProcessBuilder::create($arguments)->getProcess();
	}

}
