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
	 * @var array
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
	 * Overwrites cached value regardless of it's expiration/invalidation settings.
	 *
	 * @var boolean
	 */
	private $_cacheOverwrite = false;

	/**
	 * Creates a command instance.
	 *
	 * @param array           $command_line    Command line.
	 * @param ConsoleIO       $io              Console IO.
	 * @param CacheManager    $cache_manager   Cache manager.
	 * @param IProcessFactory $process_factory Process factory.
	 */
	public function __construct(
		array $command_line,
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
	 * Set cache invalidator.
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
	 * Set cache overwrite.
	 *
	 * @param boolean $cache_overwrite Cache replace.
	 *
	 * @return self
	 */
	public function setCacheOverwrite($cache_overwrite)
	{
		$this->_cacheOverwrite = $cache_overwrite;

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
		$output = null;
		$cache_key = $this->_getCacheKey();

		if ( $cache_key ) {
			if ( $this->_cacheOverwrite ) {
				$this->_cacheManager->deleteCache($cache_key, $this->_cacheDuration);
			}
			else {
				$output = $this->_cacheManager->getCache($cache_key, $this->_cacheInvalidator, $this->_cacheDuration);
			}

			if ( isset($output) ) {
				if ( $this->_io->isVerbose() ) {
					$this->_io->writeln('<debug>[svn, cached]: ' . $this . '</debug>');
				}

				if ( $this->_io->isDebug() ) {
					$this->_io->writeln($output, OutputInterface::OUTPUT_RAW);
				}

				if ( is_callable($callback) ) {
					call_user_func($callback, Process::OUT, $output);
				}
			}
		}

		if ( !isset($output) ) {
			$output = $this->_doRun($callback);

			if ( $cache_key ) {
				$this->_cacheManager->setCache($cache_key, $output, $this->_cacheInvalidator, $this->_cacheDuration);
			}
		}

		if ( in_array('--xml', $this->_commandLine) ) {
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
			$command_string = (string)$this;

			if ( preg_match(Connector::URL_REGEXP, $command_string, $regs) ) {
				return $regs[2] . $regs[3] . $regs[4] . '/command:' . $command_string;
			}

			return 'misc/command:' . $command_string;
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
		$process = $this->_processFactory->createProcess($this->_commandLine, 180); // Idle timeout: 3 minutes.
		$command_string = (string)$this;

		try {
			if ( $this->_io->isVerbose() ) {
				$this->_io->writeln('');
				$this->_io->write('<debug>[svn, ' . date('H:i:s') . '... ]: ' . $command_string . '</debug>');

				$start = microtime(true);
				$process->mustRun($callback);

				$runtime = sprintf('%01.2f', microtime(true) - $start);
				$this->_io->write(
					"\033[2K\r" . '<debug>[svn, ' . round($runtime, 2) . 's]: ' . $command_string . '</debug>'
				);
				$this->_io->writeln('');
			}
			else {
				$process->mustRun($callback);
			}

			$output = (string)$process->getOutput();

			if ( $this->_io->isDebug() ) {
				$this->_io->writeln($output, OutputInterface::OUTPUT_RAW);
			}

			return $output;
		}
		catch ( ProcessFailedException $e ) {
			throw new RepositoryCommandException(
				$command_string,
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

	/**
	 * Returns a string representation of a command.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return implode(' ', $this->_commandLine);
	}

}
