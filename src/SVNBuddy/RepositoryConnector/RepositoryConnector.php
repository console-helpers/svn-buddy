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
use aik099\SVNBuddy\Config\ConfigEditor;
use aik099\SVNBuddy\Exception\RepositoryCommandException;
use aik099\SVNBuddy\ConsoleIO;
use aik099\SVNBuddy\Process\IProcessFactory;

/**
 * Executes command on the repository.
 *
 * @method \Mockery\Expectation shouldReceive(string $name)
 */
class RepositoryConnector
{

	const LAST_REVISION_CACHE = '25 minutes';

	const STATUS_UNVERSIONED = 'unversioned';

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
	 * Cache duration for next invoked command.
	 *
	 * @var mixed
	 */
	private $_nextCommandCacheDuration = null;

	/**
	 * Creates repository connector.
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

		if ( $username ) {
			$this->_svnCommand .= ' --username ' . $username;
		}

		if ( $password ) {
			$this->_svnCommand .= ' --password ' . $password;
		}
	}

	/**
	 * Builds a command
	 *
	 * @param string      $command      Command.
	 * @param string|null $param_string Parameter string.
	 *
	 * @return RepositoryCommand
	 */
	public function getCommand($command, $param_string = null)
	{
		$final_command = $this->buildCommand($command, $param_string);

		$repository_command = new RepositoryCommand(
			$this->_processFactory->createProcess($final_command, 1200),
			$this->_io,
			$this->_cacheManager
		);

		if ( isset($this->_nextCommandCacheDuration) ) {
			$repository_command->setCacheDuration($this->_nextCommandCacheDuration);
			$this->_nextCommandCacheDuration = null;
		}

		return $repository_command;
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
			throw new \InvalidArgumentException('The "' . $sub_command . '" sub-command contains spaces');
		}

		$final_command = $this->_svnCommand . ' ' . $sub_command;

		if ( !empty($param_string) ) {
			$final_command .= ' ' . $param_string;
		}

		$final_command = preg_replace_callback(
			'/\{([^\}]*)\}/',
			function (array $matches) {
				return escapeshellarg($matches[1]);
			},
			$final_command
		);

		return $final_command;
	}

	/**
	 * Sets cache configuration for next created command.
	 *
	 * @param mixed $cache_duration Cache duration.
	 *
	 * @return self
	 */
	public function withCache($cache_duration)
	{
		$this->_nextCommandCacheDuration = $cache_duration;

		return $this;
	}

	/**
	 * Returns property value.
	 *
	 * @param string $name        Property name.
	 * @param string $path_or_url Path to get property from.
	 * @param mixed  $revision    Revision.
	 *
	 * @return string
	 */
	public function getProperty($name, $path_or_url, $revision = null)
	{
		$param_string = $name . ' {' . $path_or_url . '}';

		if ( isset($revision) ) {
			$param_string .= ' --revision ' . $revision;
		}

		return $this->getCommand('propget', $param_string)->run();
	}

	/**
	 * Returns path component from absolute URL to repository.
	 *
	 * @param string $absolute_url URL.
	 *
	 * @return string
	 */
	public function getPathFromUrl($absolute_url)
	{
		return parse_url($absolute_url, PHP_URL_PATH);
	}

	/**
	 * Returns URL of the working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 * @throws RepositoryCommandException When repository command failed to execute.
	 */
	public function getWorkingCopyUrl($wc_path)
	{
		if ( $this->isUrl($wc_path) ) {
			return $wc_path;
		}

		try {
			$wc_url = (string)$this->getSvnInfoEntry($wc_path)->url;
		}
		catch ( RepositoryCommandException $e ) {
			if ( $e->getCode() == RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED ) {
				$message = explode(PHP_EOL, $e->getMessage());

				$this->_io->writeln(array('', '<error>' . end($message) . '</error>', ''));

				if ( $this->_io->askConfirmation('Run "svn upgrade"', false) ) {
					$this->getCommand('upgrade', '{' . $wc_path . '}')->runLive();

					return $this->getWorkingCopyUrl($wc_path);
				}
			}

			throw $e;
		}

		return $wc_url;
	}

	/**
	 * Returns last changed revision on path/url.
	 *
	 * @param string $path_or_url    Path or url.
	 * @param mixed  $cache_duration Cache duration.
	 *
	 * @return integer
	 */
	public function getLastRevision($path_or_url, $cache_duration = null)
	{
		// Cache "svn info" commands to remote urls, not the working copy.
		if ( !isset($cache_duration) && $this->isUrl($path_or_url) ) {
			$cache_duration = self::LAST_REVISION_CACHE;
		}

		$svn_info = $this->withCache($cache_duration)->getSvnInfoEntry($path_or_url);

		return (int)$svn_info->commit['revision'];
	}

	/**
	 * Determines if given path is in fact an url.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	public function isUrl($path)
	{
		return strpos($path, '://') !== false;
	}

	/**
	 * Returns "svn info" entry for path or url.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return \SimpleXMLElement
	 * @throws \LogicException When unexpected 'svn info' results retrieved.
	 */
	protected function getSvnInfoEntry($path_or_url)
	{
		$svn_info = $this->getCommand('info', '--xml {' . $path_or_url . '}')->run();

		foreach ( $svn_info->entry as $entry ) {
			if ( $entry['kind'] != 'dir' ) {
				continue;
			}

			// When getting remote "svn info", then path is last folder only.
			if ( basename($this->getSvnInfoEntryPath($entry)) == basename($path_or_url) ) {
				return $entry;
			}
		}

		$error_msg = 'The directory "' . $path_or_url . '" not found in "svn info" command results.';

		if ( $this->_io->isVerbose() ) {
			$xml = str_replace(array('<info>', '</info>'), array('<root>', '</root>'), $svn_info->asXML());
			$error_msg .= PHP_EOL . ' XML:' . PHP_EOL . $xml;
		}

		throw new \LogicException($error_msg);
	}

	/**
	 * Returns path of "svn info" entry.
	 *
	 * @param \SimpleXMLElement $svn_info_entry The "entry" node of "svn info" command.
	 *
	 * @return string
	 */
	protected function getSvnInfoEntryPath(\SimpleXMLElement $svn_info_entry)
	{
		// SVN 1.7+.
		$path = (string)$svn_info_entry->{'wc-info'}->{'wcroot-abspath'};

		if ( !$path ) {
			// SVN 1.6-.
			$path = (string)$svn_info_entry['path'];
		}

		return $path;
	}

	/**
	 * Returns revision, when path was added to repository.
	 *
	 * @param string $url Url.
	 *
	 * @return integer
	 */
	public function getFirstRevision($url)
	{
		$log = $this->getCommand('log', ' -r 1:HEAD --limit 1 --xml {' . $url . '}')->run();

		return (int)$log->logentry['revision'];
	}

	/**
	 * Returns conflicts in working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getWorkingCopyConflicts($wc_path)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path) as $path => $status ) {
			if ( $status['item'] == 'conflicted' || $status['props'] == 'conflicted' ) {
				$ret[] = $path;
			}
		}

		return $ret;
	}

	/**
	 * Returns compact working copy status.
	 *
	 * @param string  $wc_path          Working copy path.
	 * @param boolean $with_unversioned With unversioned.
	 *
	 * @return string
	 */
	public function getCompactWorkingCopyStatus($wc_path, $with_unversioned = true)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path) as $path => $status ) {
			if ( !$with_unversioned && $status['item'] == self::STATUS_UNVERSIONED ) {
				continue;
			}

			$line = $this->getShortItemStatus($status['item']) . $this->getShortPropertiesStatus($status['props']);
			$line .= '   ' . $path;

			$ret[] = $line;
		}

		return implode(PHP_EOL, $ret);
	}

	/**
	 * Returns short item status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When unknown status given.
	 */
	protected function getShortItemStatus($status)
	{
		$status_map = array(
			'added' => 'A',
			'conflicted' => 'C',
			'deleted' => 'D',
			'external' => 'X',
			'ignored' => 'I',
			// 'incomplete' => '',
			// 'merged' => '',
			'missing' => '!',
			'modified' => 'M',
			'none' => ' ',
			'normal' => '_',
			// 'obstructed' => '',
			'replaced' => 'R',
			'unversioned' => '?',
		);

		if ( !isset($status_map[$status]) ) {
			throw new \InvalidArgumentException('The "' . $status . '" item status is unknown.');
		}

		return $status_map[$status];
	}

	/**
	 * Returns short item status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When unknown status given.
	 */
	protected function getShortPropertiesStatus($status)
	{
		$status_map = array(
			'conflicted' => 'C',
			'modified' => 'M',
			'normal' => '_',
			'none' => ' ',
		);

		if ( !isset($status_map[$status]) ) {
			throw new \InvalidArgumentException('The "' . $status . '" properties status is unknown.');
		}

		return $status_map[$status];
	}

	/**
	 * Returns working copy status.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	protected function getWorkingCopyStatus($wc_path)
	{
		$ret = array();
		$status = $this->getCommand('status', '--xml {' . $wc_path . '}')->run();

		foreach ( $status->target as $target ) {
			if ( (string)$target['path'] !== $wc_path ) {
				continue;
			}

			foreach ( $target as $entry ) {
				$path = (string)$entry['path'];

				if ( $path === $wc_path ) {
					$path = '.';
				}
				else {
					$path = str_replace($wc_path . '/', '', $path);
				}

				$ret[$path] = array(
					'item' => (string)$entry->{'wc-status'}['item'],
					'props' => (string)$entry->{'wc-status'}['props'],
				);
			}
		}

		return $ret;
	}

	/**
	 * Determines if working copy contains mixed revisions.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function isMixedRevisionWorkingCopy($wc_path)
	{
		$revisions = array();
		$status = $this->getCommand('status', '--xml --verbose {' . $wc_path . '}')->run();

		foreach ( $status->target as $target ) {
			if ( (string)$target['path'] !== $wc_path ) {
				continue;
			}

			foreach ( $target as $entry ) {
				$item_status = (string)$entry->{'wc-status'}['item'];

				if ( $item_status !== self::STATUS_UNVERSIONED ) {
					$revision = (int)$entry->{'wc-status'}['revision'];
					$revisions[$revision] = true;
				}
			}
		}

		return count($revisions) > 1;
	}

	/**
	 * Determines if there is a working copy on a given path.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 * @throws \InvalidArgumentException When path isn't found.
	 * @throws RepositoryCommandException When repository command failed to execute.
	 */
	public function isWorkingCopy($path)
	{
		if ( $this->isUrl($path) || !file_exists($path) || !is_dir($path) ) {
			throw new \InvalidArgumentException('Path not found or isn\'t a directory.');
		}

		try {
			$wc_url = $this->getWorkingCopyUrl($path);
		}
		catch ( RepositoryCommandException $e ) {
			if ( $e->getCode() == RepositoryCommandException::SVN_ERR_WC_NOT_WORKING_COPY ) {
				return false;
			}

			throw $e;
		}

		return $wc_url != '';
	}

}
