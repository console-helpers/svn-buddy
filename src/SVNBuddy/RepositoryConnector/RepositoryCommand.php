<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\RepositoryConnector;


use aik099\SVNBuddy\Cache\CacheManager;
use aik099\SVNBuddy\Exception\RepositoryConnectorException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RepositoryCommand
{

	/**
	 * Process.
	 *
	 * @var Process
	 */
	private $_process;

	/**
	 * Output.
	 *
	 * @var OutputInterface
	 */
	private $_output;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Is command interactive.
	 *
	 * @var boolean
	 */
	private $_isInteractive = false;

	/**
	 * Cache duration.
	 *
	 * @var mixed
	 */
	private $_cacheDuration;

	/**
	 * Text that when different from cached will invalidate the cache.
	 *
	 * @var string
	 */
	private $_cacheInvalidator;

	/**
	 * Creates a command instance.
	 *
	 * @param Process         $process       Process.
	 * @param OutputInterface $output        Output.
	 * @param CacheManager    $cache_manager Cache manager.
	 */
	public function __construct(Process $process, OutputInterface $output, CacheManager $cache_manager)
	{
		$this->_process = $process;
		$this->_output = $output;
		$this->_cacheManager = $cache_manager;
	}

	/**
	 * Changes command interactivity status.
	 *
	 * @param boolean $is_interactive Is interactive.
	 *
	 * @return self
	 */
	public function setInteractive($is_interactive)
	{
		$this->_isInteractive = $is_interactive;

		return $this;
	}

	/**
	 * Set cache duration.
	 *
	 * @param integer $invalidator Invalidator.
	 *
	 * @return self
	 */
	public function setCacheInvalidator($invalidator)
	{
		$this->_cacheInvalidator = $invalidator;

		return $this;
	}

	/**
	 * Set cache duration.
	 *
	 * @param mixed $duration Duration (seconds if numeric OR whatever "strtotime" accepts).
	 *
	 * @return self
	 */
	public function setCacheDuration($duration)
	{
		$this->_cacheDuration = $duration;

		return $this;
	}

	/**
	 * Runs the command.
	 *
	 * @param callable|null $callback Callback.
	 *
	 * @return string|\SimpleXMLElement
	 */
	public function run($callback = null)
	{
		$this->_patchProcess();
		$command_line = $this->_process->getCommandLine();
		$cache_key = $this->_getCacheKey();

		if ( $cache_key ) {
			$output = $this->_cacheManager->getCache($cache_key, $this->_cacheInvalidator);

			if ( isset($output) && is_callable($callback) ) {
				call_user_func($callback, Process::OUT, $output);
			}
		}

		if ( !isset($output) ) {
			$output = $this->_doRun($callback);

			if ( $cache_key ) {
				$this->_cacheManager->setCache($cache_key, $output, $this->_cacheInvalidator, $this->_cacheDuration);
			}
		}

		// Callback most likely processed output anyway.
		if ( is_callable($callback) ) {
			return null;
		}

		if ( strpos($command_line, '--xml') !== false ) {
			return simplexml_load_string($output);
		}

		return $output;
	}

	/**
	 * Runs the command.
	 *
	 * @param callable|null $callback Callback.
	 *
	 * @return string
	 * @throws RepositoryConnectorException When command execution failed.
	 */
	private function _doRun($callback = null)
	{
		try {
			$start = microtime(true);
			$this->_process->mustRun($callback);

			if ( $this->_output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE ) {
				$runtime = sprintf('%01.2f', microtime(true) - $start);
				$command_line = $this->_process->getCommandLine();
				$this->_output->writeln(
					PHP_EOL . '<fg=white;bg=magenta>[svn, ' . round($runtime, 2) . 's]: ' . $command_line . '</>'
				);
			}
		}
		catch ( ProcessFailedException $e ) {
			$process = $e->getProcess();

			throw new RepositoryConnectorException(
				$process->getCommandLine(),
				$process->getExitCode(),
				$process->getOutput(),
				$process->getErrorOutput()
			);
		}

		return (string)$this->_process->getOutput();
	}

	/**
	 * Patches process command line for interactivity.
	 *
	 * @return void
	 */
	private function _patchProcess()
	{
		$interactivity_part = ' --non-interactive ';
		$command_line = $this->_process->getCommandLine();

		if ( $this->_isInteractive ) {
			$command_line = str_replace($interactivity_part, ' ', $command_line);
			$this->_process->setInput(STDIN);
		}
		elseif ( strpos($command_line, $interactivity_part) === false ) {
			$parts = explode(' ', $command_line, 2);
			$command_line = $parts[0] . ' --non-interactive ' . $parts[1];
			$this->_process->setInput('');
		}

		$this->_process->setCommandLine($command_line);
	}

	/**
	 * Returns cache key for a command.
	 *
	 * @return string
	 */
	private function _getCacheKey()
	{
		if ( $this->_cacheInvalidator || $this->_cacheDuration ) {
			return 'command:' . $this->_process->getCommandLine();
		}

		return '';
	}

	/**
	 * Runs an svn command and displays output in real time.
	 *
	 * @param array $replacements Replacements for the output.
	 *
	 * @return string
	 */
	public function runLive(array $replacements = array())
	{
		return $this->run($this->_createLiveOutputCallback($replacements));
	}

	/**
	 * Creates "live output" callback.
	 *
	 * @param array $replacements Replacements for the output.
	 *
	 * @return callable
	 */
	private function _createLiveOutputCallback(array $replacements = array())
	{
		$output = $this->_output;

		$replace_from = array_keys($replacements);
		$replace_to = array_values($replacements);

		return function ($type, $buffer) use ($output, $replace_from, $replace_to) {
			$buffer = str_replace($replace_from, $replace_to, $buffer);

			if ( $type === Process::ERR ) {
				$buffer = '<error>ERR:</error> ' . $buffer;
			}

			$output->write($buffer);
		};
	}

}
