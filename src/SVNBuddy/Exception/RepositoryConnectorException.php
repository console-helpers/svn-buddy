<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Exception;


class RepositoryConnectorException extends AbstractException
{

	/**
	 * Command that was executed.
	 *
	 * @var string
	 */
	private $_command;

	/**
	 * Exit code.
	 *
	 * @var integer
	 */
	private $_exitCode = 0;

	/**
	 * Output from STDOUT.
	 *
	 * @var string
	 */
	private $_output;

	/**
	 * Output from STDERR.
	 *
	 * @var string
	 */
	private $_errorOutput;

	/**
	 * Creates instance of repository command execution exception.
	 *
	 * @param string  $command   Command.
	 * @param integer $exit_code Exit code.
	 * @param string  $stdout    Output from STDOUT.
	 * @param string  $stderr    Output from STDERR.
	 */
	public function __construct($command, $exit_code, $stdout, $stderr)
	{
		parent::__construct('Execution of repository command "' . $command . '" failed with output: ' . $stderr);

		$this->_command = $command;
		$this->_exitCode = $exit_code;
		$this->_output = $stdout;
		$this->_errorOutput = $stderr;
	}

	/**
	 * Returns command.
	 *
	 * @return string
	 */
	public function getCommand()
	{
		return $this->_command;
	}

	/**
	 * Returns exit code.
	 *
	 * @return integer
	 */
	public function getExitCode()
	{
		return $this->_exitCode;
	}

	/**
	 * Returns command output.
	 *
	 * @return string
	 */
	public function getOutput()
	{
		return $this->_output;
	}

	/**
	 * Returns command error output.
	 *
	 * @return string
	 */
	public function getErrorOutput()
	{
		return $this->_errorOutput;
	}

}
