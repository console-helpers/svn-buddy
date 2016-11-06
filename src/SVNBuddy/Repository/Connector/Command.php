<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\Connector;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Command
{

	/**
	 * Process factory.
	 *
	 * @var IProcessFactory
	 */
	private $_processFactory;

	/**
	 * Command line.
	 *
	 * @var string
	 */
	private $_commandLine;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

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
	 * @param string          $command_line    Command line.
	 * @param ConsoleIO       $io              Console IO.
	 * @param CacheManager    $cache_manager   Cache manager.
	 * @param IProcessFactory $process_factory Process factory.
	 */
	public function __construct(
		$command_line,
		ConsoleIO $io,
		CacheManager $cache_manager,
		IProcessFactory $process_factory
	) {
		$this->_commandLine = $command_line;
		$this->_io = $io;
		$this->_cacheManager = $cache_manager;
		$this->_processFactory = $process_factory;
	}

	/**
	 * Set cache duration.
	 *
	 * @param string $invalidator Invalidator.
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
		$cache_key = $this->_getCacheKey();

		if ( $cache_key ) {
			$output = $this->_cacheManager->getCache($cache_key, $this->_cacheInvalidator, $this->_cacheDuration);

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

		if ( strpos($this->_commandLine, '--xml') !== false ) {
			return simplexml_load_string($output);
		}

		return $output;
	}

	/**
	 * Returns cache key for a command.
	 *
	 * @return string
	 */
	private function _getCacheKey()
	{
		if ( $this->_cacheInvalidator || $this->_cacheDuration ) {
			if ( preg_match(Connector::URL_REGEXP, $this->_commandLine, $regs) ) {
				return $regs[2] . $regs[3] . $regs[4] . '/command:' . $this->_commandLine;
			}

			return 'misc/command:' . $this->_commandLine;
		}

		return '';
	}

	/**
	 * Runs the command.
	 *
	 * @param callable|null $callback Callback.
	 *
	 * @return string
	 * @throws RepositoryCommandException When command execution failed.
	 */
	private function _doRun($callback = null)
	{
		$process = $this->_processFactory->createProcess($this->_commandLine, 1200);

		try {
			$start = microtime(true);
			$process->mustRun($callback);

			if ( $this->_io->isVerbose() ) {
				$runtime = sprintf('%01.2f', microtime(true) - $start);
				$this->_io->writeln(
					array('', '<debug>[svn, ' . round($runtime, 2) . 's]: ' . $this->_commandLine . '</debug>')
				);
			}

			$output = (string)$process->getOutput();

			if ( $this->_io->isDebug() ) {
				$this->_io->writeln($output, OutputInterface::OUTPUT_RAW);
			}

			return $output;
		}
		catch ( ProcessFailedException $e ) {
			throw new RepositoryCommandException(
				$this->_commandLine,
				$process->getErrorOutput()
			);
		}
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
		$io = $this->_io;

		$replace_froms = array_keys($replacements);
		$replace_tos = array_values($replacements);

		return function ($type, $buffer) use ($io, $replace_froms, $replace_tos) {
			foreach ( $replace_froms as $index => $replace_from ) {
				$replace_to = $replace_tos[$index];

				if ( substr($replace_from, 0, 1) === '/' && substr($replace_from, -1, 1) === '/' ) {
					$buffer = preg_replace($replace_from, $replace_to, $buffer);
				}
				else {
					$buffer = str_replace($replace_from, $replace_to, $buffer);
				}
			}

			if ( $type === Process::ERR ) {
				$buffer = '<error>ERR:</error> ' . $buffer;
			}

			$io->write($buffer);
		};
	}

}
