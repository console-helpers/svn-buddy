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


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;

class CommandFactory
{

	/**
	 * Reference to configuration.
	 *
	 * @var ConfigEditor
	 */
	private $_configEditor;

	/**
	 * Process factory.
	 *
	 * @var IProcessFactory
	 */
	private $_processFactory;

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
	 * Path to an svn command.
	 *
	 * @var string
	 */
	private $_svnCommand = 'svn';

	/**
	 * Creates command factory.
	 *
	 * @param ConfigEditor    $config_editor   ConfigEditor.
	 * @param IProcessFactory $process_factory Process factory.
	 * @param ConsoleIO       $io              Console IO.
	 * @param CacheManager    $cache_manager   Cache manager.
	 */
	public function __construct(
		ConfigEditor $config_editor,
		IProcessFactory $process_factory,
		ConsoleIO $io,
		CacheManager $cache_manager
	) {
		$this->_configEditor = $config_editor;
		$this->_processFactory = $process_factory;
		$this->_io = $io;
		$this->_cacheManager = $cache_manager;

		$this->prepareSvnCommand();
	}

	/**
	 * Prepares static part of svn command to be used across the script.
	 *
	 * @return void
	 */
	protected function prepareSvnCommand()
	{
		$username = $this->_configEditor->get('repository-connector.username');
		$password = $this->_configEditor->get('repository-connector.password');

		$this->_svnCommand .= ' --non-interactive';

		if ( $username ) {
			$this->_svnCommand .= ' --username ' . $username;
		}

		if ( $password ) {
			$this->_svnCommand .= ' --password ' . $password;
		}
	}

	/**
	 * Builds a command.
	 *
	 * @param string      $sub_command  Sub command.
	 * @param string|null $param_string Parameter string.
	 *
	 * @return Command
	 */
	public function getCommand($sub_command, $param_string = null)
	{
		$command_line = $this->buildCommand($sub_command, $param_string);

		return new Command(
			$command_line,
			$this->_io,
			$this->_cacheManager,
			$this->_processFactory
		);
	}

	/**
	 * Builds command from given arguments.
	 *
	 * @param string $sub_command  Command.
	 * @param string $param_string Parameter string.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When command contains spaces.
	 */
	protected function buildCommand($sub_command, $param_string = null)
	{
		if ( strpos($sub_command, ' ') !== false ) {
			throw new \InvalidArgumentException('The "' . $sub_command . '" sub-command contains spaces.');
		}

		$command_line = $this->_svnCommand;

		if ( !empty($sub_command) ) {
			$command_line .= ' ' . $sub_command;
		}

		if ( !empty($param_string) ) {
			$command_line .= ' ' . $param_string;
		}

		$command_line = preg_replace_callback(
			'/\{([^\}]*)\}/',
			function (array $matches) {
				return escapeshellarg($matches[1]);
			},
			$command_line
		);

		return $command_line;
	}

}
