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
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;

/**
 * Executes command on the repository.
 */
class Connector
{

	const STATUS_NORMAL = 'normal';

	const STATUS_ADDED = 'added';

	const STATUS_CONFLICTED = 'conflicted';

	const STATUS_UNVERSIONED = 'unversioned';

	const STATUS_EXTERNAL = 'external';

	const STATUS_MISSING = 'missing';

	const STATUS_NONE = 'none';

	const URL_REGEXP = '#([\w]*)://([^/@\s\']+@)?([^/@:\s\']+)(:\d+)?([^@\s\']*)?#';

	const SVN_INFO_CACHE_DURATION = '1 year';

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
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

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
	 * Whatever to cache last repository revision or not.
	 *
	 * @var mixed
	 */
	private $_lastRevisionCacheDuration = null;

	/**
	 * Creates repository connector.
	 *
	 * @param ConfigEditor       $config_editor        ConfigEditor.
	 * @param IProcessFactory    $process_factory      Process factory.
	 * @param ConsoleIO          $io                   Console IO.
	 * @param CacheManager       $cache_manager        Cache manager.
	 * @param RevisionListParser $revision_list_parser Revision list parser.
	 */
	public function __construct(
		ConfigEditor $config_editor,
		IProcessFactory $process_factory,
		ConsoleIO $io,
		CacheManager $cache_manager,
		RevisionListParser $revision_list_parser
	) {
		$this->_configEditor = $config_editor;
		$this->_processFactory = $process_factory;
		$this->_io = $io;
		$this->_cacheManager = $cache_manager;
		$this->_revisionListParser = $revision_list_parser;

		$cache_duration = $this->_configEditor->get('repository-connector.last-revision-cache-duration');

		if ( (string)$cache_duration === '' || substr($cache_duration, 0, 1) === '0' ) {
			$cache_duration = 0;
		}

		$this->_lastRevisionCacheDuration = $cache_duration;

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

		$command = new Command(
			$command_line,
			$this->_io,
			$this->_cacheManager,
			$this->_processFactory
		);

		if ( isset($this->_nextCommandCacheDuration) ) {
			$command->setCacheDuration($this->_nextCommandCacheDuration);
			$this->_nextCommandCacheDuration = null;
		}

		return $command;
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
	 * Returns relative path of given path/url to the root of the repository.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return string
	 */
	public function getRelativePath($path_or_url)
	{
		$svn_info_entry = $this->_getSvnInfoEntry($path_or_url, self::SVN_INFO_CACHE_DURATION);

		return preg_replace(
			'/^' . preg_quote($svn_info_entry->repository->root, '/') . '/',
			'',
			(string)$svn_info_entry->url,
			1
		);
	}

	/**
	 * Returns repository root url from given path/url.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return string
	 */
	public function getRootUrl($path_or_url)
	{
		return (string)$this->_getSvnInfoEntry($path_or_url, self::SVN_INFO_CACHE_DURATION)->repository->root;
	}

	/**
	 * Determines if path is a root of the ref.
	 *
	 * @param string $path Path to a file.
	 *
	 * @return boolean
	 */
	public function isRefRoot($path)
	{
		$ref = $this->getRefByPath($path);

		if ( $ref === false ) {
			return false;
		}

		return preg_match('#/' . preg_quote($ref, '#') . '/$#', $path) > 0;
	}

	/**
	 * Detects ref from given path.
	 *
	 * @param string $path Path to a file.
	 *
	 * @return string|boolean
	 * @see    getProjectUrl
	 */
	public function getRefByPath($path)
	{
		if ( preg_match('#^.*?/(trunk|branches/[^/]+|tags/[^/]+|releases/[^/]+).*$#', $path, $regs) ) {
			return $regs[1];
		}

		return false;
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
			return $this->removeCredentials($wc_path);
		}

		try {
			$wc_url = (string)$this->_getSvnInfoEntry($wc_path, self::SVN_INFO_CACHE_DURATION)->url;
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
	 * @param string $path_or_url Path or url.
	 *
	 * @return integer
	 */
	public function getLastRevision($path_or_url)
	{
		// Cache "svn info" commands to remote urls, not the working copy.
		$cache_duration = $this->isUrl($path_or_url) ? $this->_lastRevisionCacheDuration : null;

		return (int)$this->_getSvnInfoEntry($path_or_url, $cache_duration)->commit['revision'];
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
	 * Removes credentials from url.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When non-url given.
	 */
	public function removeCredentials($url)
	{
		if ( !$this->isUrl($url) ) {
			throw new \InvalidArgumentException('Unable to remove credentials from "' . $url . '" path.');
		}

		return preg_replace('#^(.*)://(.*)@(.*)$#', '$1://$3', $url);
	}

	/**
	 * Returns project url (container for "trunk/branches/tags/releases" folders).
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return string
	 * @see    getRefByPath
	 */
	public function getProjectUrl($repository_url)
	{
		if ( preg_match('#^(.*?)/(trunk|branches|tags|releases).*$#', $repository_url, $regs) ) {
			return $regs[1];
		}

		// When known folder structure not detected consider, that project url was already given.
		return $repository_url;
	}

	/**
	 * Returns "svn info" entry for path or url.
	 *
	 * @param string $path_or_url    Path or url.
	 * @param mixed  $cache_duration Cache duration.
	 *
	 * @return \SimpleXMLElement
	 * @throws \LogicException When unexpected 'svn info' results retrieved.
	 */
	private function _getSvnInfoEntry($path_or_url, $cache_duration = null)
	{
		// Cache "svn info" commands to remote urls, not the working copy.
		if ( !isset($cache_duration) && $this->isUrl($path_or_url) ) {
			$cache_duration = self::SVN_INFO_CACHE_DURATION;
		}

		// Remove credentials from url, because "svn info" fails, when used on repository root.
		if ( $this->isUrl($path_or_url) ) {
			$path_or_url = $this->removeCredentials($path_or_url);
		}

		// TODO: When wc path (not url) is given, then credentials can be present in "svn info" result anyway.
		$svn_info = $this->withCache($cache_duration)->getCommand('info', '--xml {' . $path_or_url . '}')->run();

		// When getting remote "svn info", then path is last folder only.
		$svn_info_path = $this->_getSvnInfoEntryPath($svn_info->entry);

		// In SVN 1.7+, when doing "svn info" on repository root url.
		if ( $svn_info_path === '.' ) {
			$svn_info_path = $path_or_url;
		}

		if ( basename($svn_info_path) != basename($path_or_url) ) {
			throw new \LogicException('The directory "' . $path_or_url . '" not found in "svn info" command results.');
		}

		return $svn_info->entry;
	}

	/**
	 * Returns path of "svn info" entry.
	 *
	 * @param \SimpleXMLElement $svn_info_entry The "entry" node of "svn info" command.
	 *
	 * @return string
	 */
	private function _getSvnInfoEntryPath(\SimpleXMLElement $svn_info_entry)
	{
		// SVN 1.7+.
		$path = (string)$svn_info_entry->{'wc-info'}->{'wcroot-abspath'};

		if ( $path ) {
			return $path;
		}

		// SVN 1.6-.
		return (string)$svn_info_entry['path'];
	}

	/**
	 * Returns revision, when path was added to repository.
	 *
	 * @param string $url Url.
	 *
	 * @return integer
	 * @throws \InvalidArgumentException When not an url was given.
	 */
	public function getFirstRevision($url)
	{
		if ( !$this->isUrl($url) ) {
			throw new \InvalidArgumentException('The repository URL "' . $url . '" is invalid.');
		}

		$log = $this->withCache('1 year')->getCommand('log', ' -r 1:HEAD --limit 1 --xml {' . $url . '}')->run();

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
			if ( $this->isWorkingCopyPathStatus($status, self::STATUS_CONFLICTED) ) {
				$ret[] = $path;
			}
		}

		return $ret;
	}

	/**
	 * Returns missing paths in working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getWorkingCopyMissing($wc_path)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path) as $path => $status ) {
			if ( $this->isWorkingCopyPathStatus($status, self::STATUS_MISSING) ) {
				$ret[] = $path;
			}
		}

		return $ret;
	}

	/**
	 * Returns compact working copy status.
	 *
	 * @param string      $wc_path          Working copy path.
	 * @param string|null $changelist       Changelist.
	 * @param boolean     $with_unversioned With unversioned.
	 *
	 * @return string
	 */
	public function getCompactWorkingCopyStatus($wc_path, $changelist = null, $with_unversioned = false)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path, $changelist, $with_unversioned) as $path => $status ) {
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
	 * @param string      $wc_path          Working copy path.
	 * @param string|null $changelist       Changelist.
	 * @param boolean     $with_unversioned With unversioned.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When changelist doens't exist.
	 */
	public function getWorkingCopyStatus($wc_path, $changelist = null, $with_unversioned = false)
	{
		$all_paths = array();

		$status = $this->getCommand('status', '--xml {' . $wc_path . '}')->run();

		if ( !strlen($changelist) ) {
			// Accept all entries from "target" and "changelist" nodes.
			foreach ( $status->children() as $entries ) {
				$child_name = $entries->getName();

				if ( $child_name === 'target' || $child_name === 'changelist' ) {
					$all_paths += $this->processStatusEntryNodes($wc_path, $entries);
				}
			}
		}
		else {
			// Accept all entries from "changelist" node and parent folders from "target" node.
			foreach ( $status->changelist as $changelist_entries ) {
				if ( (string)$changelist_entries['name'] === $changelist ) {
					$all_paths += $this->processStatusEntryNodes($wc_path, $changelist_entries);
				}
			}

			if ( !$all_paths ) {
				throw new \InvalidArgumentException('The "' . $changelist . '" changelist doens\'t exist.');
			}

			$parent_paths = $this->getParentPaths(array_keys($all_paths));

			foreach ( $status->target as $target_entries ) {
				foreach ( $this->processStatusEntryNodes($wc_path, $target_entries) as $path => $path_data ) {
					if ( in_array($path, $parent_paths) ) {
						$all_paths[$path] = $path_data;
					}
				}
			}

			ksort($all_paths, SORT_STRING);
		}

		// Exclude paths, that haven't changed (e.g. from changelists).
		$changed_paths = array();

		foreach ( $all_paths as $path => $status ) {
			if ( $this->isWorkingCopyPathStatus($status, self::STATUS_NORMAL) ) {
				continue;
			}

			if ( !$with_unversioned && $this->isWorkingCopyPathStatus($status, self::STATUS_UNVERSIONED) ) {
				continue;
			}

			$changed_paths[$path] = $status;
		}

		return $changed_paths;
	}

	/**
	 * Processes "entry" nodes from "svn status" command.
	 *
	 * @param string            $wc_path Working copy path.
	 * @param \SimpleXMLElement $entries Entries.
	 *
	 * @return array
	 */
	protected function processStatusEntryNodes($wc_path, \SimpleXMLElement $entries)
	{
		$ret = array();

		foreach ( $entries as $entry ) {
			$path = (string)$entry['path'];
			$path = $path === $wc_path ? '.' : str_replace($wc_path . '/', '', $path);

			$ret[$path] = array(
				'item' => (string)$entry->{'wc-status'}['item'],
				'props' => (string)$entry->{'wc-status'}['props'],
				'tree-conflicted' => (string)$entry->{'wc-status'}['tree-conflicted'] === 'true',
			);
		}

		return $ret;
	}

	/**
	 * Detects specific path status.
	 *
	 * @param array  $status      Path status.
	 * @param string $path_status Expected path status.
	 *
	 * @return boolean
	 */
	protected function isWorkingCopyPathStatus(array $status, $path_status)
	{
		$tree_conflicted = $status['tree-conflicted'];

		if ( $path_status === self::STATUS_NORMAL ) {
			// Normal if all of 3 are normal.
			return $status['item'] === $path_status && $status['props'] === $path_status && !$tree_conflicted;
		}
		elseif ( $path_status === self::STATUS_CONFLICTED ) {
			// Conflict if any of 3 has conflict.
			return $status['item'] === $path_status || $status['props'] === $path_status || $tree_conflicted;
		}
		elseif ( $path_status === self::STATUS_UNVERSIONED ) {
			return $status['item'] === $path_status && $status['props'] === self::STATUS_NONE;
		}

		return $status['item'] === $path_status;
	}

	/**
	 * Returns parent paths from given paths.
	 *
	 * @param array $paths Paths.
	 *
	 * @return array
	 */
	protected function getParentPaths(array $paths)
	{
		$ret = array();

		foreach ( $paths as $path ) {
			while ( $path !== '.' ) {
				$path = dirname($path);
				$ret[] = $path;
			}
		}

		return array_unique($ret);
	}

	/**
	 * Returns working copy changelists.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getWorkingCopyChangelists($wc_path)
	{
		$ret = array();
		$status = $this->getCommand('status', '--xml {' . $wc_path . '}')->run();

		foreach ( $status->changelist as $changelist ) {
			$ret[] = (string)$changelist['name'];
		}

		sort($ret, SORT_STRING);

		return $ret;
	}

	/**
	 * Returns revisions of paths in a working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getWorkingCopyRevisions($wc_path)
	{
		$revisions = array();
		$status = $this->getCommand('status', '--xml --verbose {' . $wc_path . '}')->run();

		foreach ( $status->target as $target ) {
			if ( (string)$target['path'] !== $wc_path ) {
				continue;
			}

			foreach ( $target as $entry ) {
				$revision = (int)$entry->{'wc-status'}['revision'];
				$revisions[$revision] = true;
			}
		}

		// The "-1" revision happens, when external is deleted.
		// The "0" happens for not committed paths (e.g. added).
		unset($revisions[-1], $revisions[0]);

		return array_keys($revisions);
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
		if ( $this->isUrl($path) || !file_exists($path) ) {
			throw new \InvalidArgumentException('Path "' . $path . '" not found.');
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

	/**
	 * Returns list of just merged revisions.
	 *
	 * @param string $wc_path Working copy path, where merge happens.
	 *
	 * @return array
	 */
	public function getFreshMergedRevisions($wc_path)
	{
		$final_paths = array();
		$old_paths = $this->getMergedRevisions($wc_path, 'BASE');
		$new_paths = $this->getMergedRevisions($wc_path);

		if ( $old_paths === $new_paths ) {
			return array();
		}

		foreach ( $new_paths as $new_path => $new_merged_revisions ) {
			if ( !isset($old_paths[$new_path]) ) {
				// Merge from new path.
				$final_paths[$new_path] = $this->_revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
			}
			elseif ( $new_merged_revisions != $old_paths[$new_path] ) {
				// Merge on existing path.
				$new_merged_revisions_parsed = $this->_revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
				$old_merged_revisions_parsed = $this->_revisionListParser->expandRanges(
					explode(',', $old_paths[$new_path])
				);
				$final_paths[$new_path] = array_values(
					array_diff($new_merged_revisions_parsed, $old_merged_revisions_parsed)
				);
			}
		}

		return $final_paths;
	}

	/**
	 * Returns list of merged revisions per path.
	 *
	 * @param string  $wc_path  Merge target: working copy path.
	 * @param integer $revision Revision.
	 *
	 * @return array
	 */
	protected function getMergedRevisions($wc_path, $revision = null)
	{
		$paths = array();

		$merge_info = $this->getProperty('svn:mergeinfo', $wc_path, $revision);
		$merge_info = array_filter(explode("\n", $merge_info));

		foreach ( $merge_info as $merge_info_line ) {
			list($path, $revisions) = explode(':', $merge_info_line, 2);
			$paths[$path] = $revisions;
		}

		return $paths;
	}

}
