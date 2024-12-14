<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Database;


use Aura\Sql\Profiler\ProfilerInterface;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

class StatementProfiler implements ProfilerInterface
{

	use TStatementProfiler;

	/**
	 * Is the profiler active?
	 *
	 * @var boolean
	 */
	protected $active = false;

	/**
	 * Log profile data through this interface.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The log level for all messages.
	 *
	 * @var string
	 * @see setLogLevel()
	 */
	protected $logLevel = LogLevel::DEBUG;

	/**
	 * Sets the format for the log message, with placeholders.
	 *
	 * @var string
	 * @see setLogFormat()
	 */
	protected $logFormat = '{function} ({duration} seconds): {statement} {backtrace}';

	/**
	 * Retained profiles.
	 *
	 * @var array
	 */
	protected $profiles = array();

	/**
	 * Track duplicate statements.
	 *
	 * @var boolean
	 */
	protected $trackDuplicates = true;

	/**
	 * Ignored duplicate statements.
	 *
	 * @var array
	 */
	protected $ignoredDuplicateStatements = array();

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Debug mode.
	 *
	 * @var boolean
	 */
	private $_debugMode = false;

	/**
	 * Debug backtrace options.
	 *
	 * @var integer
	 */
	private $_backtraceOptions;

	/**
	 * Creates statement profiler instance.
	 */
	public function __construct()
	{
		$this->logger = new NullLogger();
		$this->_backtraceOptions = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : 0;
	}

	/**
	 * Sets IO.
	 *
	 * @param ConsoleIO $io Console IO.
	 *
	 * @return void
	 */
	public function setIO(ConsoleIO $io = null)
	{
		$this->_io = $io;
		$this->_debugMode = isset($io) && $io->isVerbose();
	}

	/**
	 * Adds statement to ignore list.
	 *
	 * @param string $statement The SQL query statement.
	 *
	 * @return void
	 */
	public function ignoreDuplicateStatement($statement)
	{
		$this->ignoredDuplicateStatements[] = $this->normalizeStatement($statement);
	}

	/**
	 * Toggle duplicate statement tracker.
	 *
	 * @param boolean $track Duplicate statement tracker status.
	 *
	 * @return void
	 */
	public function trackDuplicates($track)
	{
		$this->trackDuplicates = (bool)$track;
	}

	/**
	 * Adds a profile entry.
	 *
	 * @param float  $duration    The query duration.
	 * @param string $function    The PDO method that made the entry.
	 * @param string $statement   The SQL query statement.
	 * @param array  $bind_values The values bound to the statement.
	 *
	 * @return void
	 * @throws \PDOException When duplicate statement is detected.
	 */
	public function addProfile(
		$duration,
		$function,
		$statement,
		array $bind_values = array()
	) {
		if ( !$this->isActive() || $function === 'prepare' || !$statement ) {
			return;
		}

		$normalized_statement = $this->normalizeStatement($statement);
		$profile_key = $this->createProfileKey($normalized_statement, $bind_values);

		if ( $this->trackDuplicates
			&& !in_array($normalized_statement, $this->ignoredDuplicateStatements)
			&& isset($this->profiles[$profile_key])
		) {
			$substituted_normalized_statement = $this->substituteParameters($normalized_statement, $bind_values);
			$error_msg = 'Duplicate statement:' . PHP_EOL . $substituted_normalized_statement;

			throw new \PDOException($error_msg);
		}

		$this->profiles[$profile_key] = array(
			'duration' => $duration,
			'function' => $function,
			'statement' => $statement,
			'bind_values' => $bind_values,
		);

		if ( $this->_debugMode ) {
			$trace = debug_backtrace($this->_backtraceOptions);

			do {
				$trace_line = array_shift($trace);
			} while ( $trace && strpos($trace_line['file'], 'aura/sql') !== false );

			$runtime = sprintf('%01.2f', $duration);
			$substituted_normalized_statement = $this->substituteParameters($normalized_statement, $bind_values);
			$trace_file = substr($trace_line['file'], strpos($trace_line['file'], '/src/')) . ':' . $trace_line['line'];
			$this->_io->writeln(array(
				'',
				'<debug>[db, ' . round($runtime, 2) . 's]: ' . $substituted_normalized_statement . '</debug>',
				'<debug>[db origin]: ' . $trace_file . '</debug>',
			));
		}
	}

	/**
	 * Removes a profile entry.
	 *
	 * @param string $statement   The SQL query statement.
	 * @param array  $bind_values The values bound to the statement.
	 *
	 * @return void
	 */
	public function removeProfile($statement, array $bind_values = array())
	{
		if ( !$this->isActive() ) {
			return;
		}

		$normalized_statement = $this->normalizeStatement($statement);
		unset($this->profiles[$this->createProfileKey($normalized_statement, $bind_values)]);
	}

	/**
	 * Normalizes statement.
	 *
	 * @param string $statement The SQL query statement.
	 *
	 * @return string
	 */
	protected function normalizeStatement($statement)
	{
		return preg_replace('/\s+/', ' ', $statement);
	}

	/**
	 * Creates profile key.
	 *
	 * @param string $normalized_statement The normalized SQL query statement.
	 * @param array  $bind_values          The values bound to the statement.
	 *
	 * @return string
	 */
	protected function createProfileKey($normalized_statement, array $bind_values = array())
	{
		return md5('statement:' . $normalized_statement . ';bind_values:' . serialize($bind_values));
	}

	/**
	 * Substitutes parameters in the statement.
	 *
	 * @param string $normalized_statement The normalized SQL query statement.
	 * @param array  $bind_values          The values bound to the statement.
	 *
	 * @return string
	 */
	protected function substituteParameters($normalized_statement, array $bind_values = array())
	{
		arsort($bind_values);

		foreach ( $bind_values as $param_name => $param_value ) {
			if ( is_array($param_value) ) {
				$param_value = '"' . implode('","', $param_value) . '"';
			}
			else {
				$param_value = '"' . $param_value . '"';
			}

			$normalized_statement = str_replace(':' . $param_name, $param_value, $normalized_statement);
		}

		return $normalized_statement;
	}

	/**
	 * Returns all the profile entries.
	 *
	 * @return array
	 */
	public function getProfiles()
	{
		return $this->profiles;
	}

	/**
	 * Reset all the profiles
	 *
	 * @return void
	 */
	public function resetProfiles()
	{
		$this->profiles = array();
	}

}
