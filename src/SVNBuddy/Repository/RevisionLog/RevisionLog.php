<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\DatabaseCollectorPlugin\IDatabaseCollectorPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RepositoryCollectorPlugin\IRepositoryCollectorPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionUrlBuilder;

class RevisionLog
{

	const FLAG_VERBOSE = 1;

	const FLAG_MERGE_HISTORY = 2;

	/**
	 * Repository path.
	 *
	 * @var string
	 */
	private $_repositoryRootUrl;

	/**
	 * Project path.
	 *
	 * @var string
	 */
	private $_projectPath;

	/**
	 * Ref name.
	 *
	 * @var string
	 */
	private $_refName;

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Installed plugins.
	 *
	 * @var IPlugin[]
	 */
	private $_plugins = array();

	/**
	 * Revision URL builder.
	 *
	 * @var RevisionUrlBuilder
	 */
	private $_revisionUrlBuilder;

	/**
	 * Force refresh flag filename.
	 *
	 * @var string
	 */
	private $_forceRefreshFlagFilename;

	/**
	 * Create revision log.
	 *
	 * @param string             $repository_url       Repository url.
	 * @param RevisionUrlBuilder $revision_url_builder Revision URL builder.
	 * @param Connector          $repository_connector Repository connector.
	 * @param string             $working_directory    Working directory.
	 * @param ConsoleIO          $io                   Console IO.
	 */
	public function __construct(
		$repository_url,
		RevisionUrlBuilder $revision_url_builder,
		Connector $repository_connector,
		$working_directory,
		ConsoleIO $io = null
	) {
		$this->_io = $io;
		$this->_repositoryConnector = $repository_connector;

		$this->_repositoryRootUrl = $repository_connector->getRootUrl($repository_url);

		$relative_path = $repository_connector->getRelativePath($repository_url);
		$this->_projectPath = $repository_connector->getProjectUrl($relative_path) . '/';
		$this->_refName = $repository_connector->getRefByPath($relative_path);
		$this->_revisionUrlBuilder = $revision_url_builder;
		$this->_forceRefreshFlagFilename = $working_directory . '/' . md5($this->_repositoryRootUrl) . '.force-refresh';
	}

	/**
	 * Returns revision URL builder.
	 *
	 * @return RevisionUrlBuilder
	 */
	public function getRevisionURLBuilder()
	{
		return $this->_revisionUrlBuilder;
	}

	/**
	 * Queries missing revisions.
	 *
	 * @param boolean $is_migration Is migration.
	 *
	 * @return void
	 * @throws \LogicException When no plugins are registered.
	 */
	public function refresh($is_migration)
	{
		if ( !$this->_plugins ) {
			throw new \LogicException('Please register at least one revision log plugin.');
		}

		$this->_databaseReady();

		if ( $is_migration ) {
			// Import missing data for imported commits only.
			$from_revision = 0;
			$to_revision = $this->_getAggregateRevision('max');
		}
		else {
			// Import all data for new commits only.
			$from_revision = $this->_getAggregateRevision('min');

			if ( $this->getForceRefreshFlag() ) {
				$this->_repositoryConnector->withCacheOverwrite(true);
				$this->setForceRefreshFlag(false);
			}

			$to_revision = $this->_repositoryConnector->getLastRevision($this->_repositoryRootUrl);
		}

		if ( $to_revision > $from_revision ) {
			$this->_queryRevisionData($from_revision, $to_revision);
		}
	}

	/**
	 * Sets force refresh flag.
	 *
	 * @param boolean $flag Flag.
	 *
	 * @return void
	 */
	public function setForceRefreshFlag($flag)
	{
		if ( $flag ) {
			touch($this->_forceRefreshFlagFilename);
		}
		else {
			unlink($this->_forceRefreshFlagFilename);
		}
	}

	/**
	 * Gets force refresh flag.
	 *
	 * @return boolean
	 */
	protected function getForceRefreshFlag()
	{
		return file_exists($this->_forceRefreshFlagFilename);
	}

	/**
	 * Reparses a revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return void
	 * @throws \LogicException When no plugins are registered.
	 */
	public function reparse($revision)
	{
		if ( !$this->_plugins ) {
			throw new \LogicException('Please register at least one revision log plugin.');
		}

		$this->_databaseReady();
		$this->_queryRevisionData($revision, $revision, true);
	}

	/**
	 * Reports to each plugin, that database is ready for usage.
	 *
	 * @return void
	 */
	private function _databaseReady()
	{
		foreach ( $this->_plugins as $plugin ) {
			$plugin->whenDatabaseReady();
		}
	}

	/**
	 * Returns aggregated revision from all plugins.
	 *
	 * @param string $function Aggregate function.
	 *
	 * @return integer
	 */
	private function _getAggregateRevision($function)
	{
		$last_revisions = array();

		foreach ( $this->_plugins as $plugin ) {
			$last_revisions[] = $plugin->getLastRevision();
		}

		if ( count($last_revisions) > 1 ) {
			return call_user_func_array($function, $last_revisions);
		}

		return current($last_revisions);
	}

	/**
	 * Queries missing revision data.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 * @param boolean $overwrite     Overwrite.
	 *
	 * @return void
	 */
	private function _queryRevisionData($from_revision, $to_revision, $overwrite = false)
	{
		$this->_useRepositoryCollectorPlugins($from_revision, $to_revision, $overwrite);
		$this->_useDatabaseCollectorPlugins($from_revision, $to_revision, $overwrite);

		if ( isset($this->_io) && $this->_io->isVerbose() ) {
			$this->_displayPluginActivityStatistics();
		}
	}

	/**
	 * Use repository collector plugins.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 * @param boolean $overwrite     Overwrite.
	 *
	 * @return void
	 */
	private function _useRepositoryCollectorPlugins($from_revision, $to_revision, $overwrite = false)
	{
		// The "io" isn't set during autocomplete.
		if ( isset($this->_io) ) {
			// Create progress bar for repository plugins, where data amount is known upfront.
			$progress_bar = $this->_io->createProgressBar(ceil(($to_revision - $from_revision) / 200) + 1);
			$progress_bar->setMessage(
				$overwrite ? '* Reparsing revisions:' : ' * Reading missing revisions:'
			);
			$progress_bar->setFormat(
				'%message% %current%/%max% [%bar%] <info>%percent:3s%%</info> %elapsed:6s%/%estimated:-6s% <info>%memory:-10s%</info>'
			);
			$progress_bar->start();
		}

		$plugins = $this->getRepositoryCollectorPlugins($overwrite);

		if ( $overwrite ) {
			$this->setPluginsOverwriteMode($plugins, true);
		}

		$range_start = $from_revision;
		$cache_duration = $overwrite ? null : '10 years';
		$log_command_arguments = $this->_getLogCommandArguments($plugins);

		while ( $range_start <= $to_revision ) {
			$range_end = min($range_start + 199, $to_revision);

			$command_arguments = str_replace(
				array('{revision_range}', '{repository_url}'),
				array($range_start . ':' . $range_end, $this->_repositoryRootUrl),
				$log_command_arguments
			);
			$command = $this->_repositoryConnector->getCommand('log', $command_arguments);
			$command->setCacheDuration($cache_duration);
			$svn_log = $command->run();

			$this->_parseLog($svn_log, $plugins);

			$range_start = $range_end + 1;

			if ( isset($progress_bar) ) {
				$progress_bar->advance();
			}
		}

		// Remove progress bar of repository plugins.
		if ( isset($progress_bar) ) {
			$progress_bar->clear();
			unset($progress_bar);
		}

		if ( $overwrite ) {
			$this->setPluginsOverwriteMode($plugins, false);
		}
	}

	/**
	 * Use database collector plugins.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 * @param boolean $overwrite     Overwrite.
	 *
	 * @return void
	 */
	private function _useDatabaseCollectorPlugins($from_revision, $to_revision, $overwrite = false)
	{
		$plugins = $this->getDatabaseCollectorPlugins($overwrite);

		if ( $overwrite ) {
			$this->setPluginsOverwriteMode($plugins, true);
		}

		// The "io" isn't set during autocomplete.
		if ( isset($this->_io) ) {
			// Create progress bar for database plugins, where data amount isn't known upfront.
			$progress_bar = $this->_io->createProgressBar();
			$progress_bar->setMessage(
				$overwrite ? '* Reparsing revisions:' : ' * Reading missing revisions:'
			);
			$progress_bar->setFormat('%message% %current% [%bar%] %elapsed:6s% <info>%memory:-10s%</info>');
			$progress_bar->start();

			foreach ( $plugins as $plugin ) {
				$plugin->process($from_revision, $to_revision, $progress_bar);
			}
		}
		else {
			foreach ( $plugins as $plugin ) {
				$plugin->process($from_revision, $to_revision);
			}
		}

		if ( $overwrite ) {
			$this->setPluginsOverwriteMode($plugins, false);
		}

		if ( isset($progress_bar) ) {
			$progress_bar->finish();
			$this->_io->writeln('');
		}
	}

	/**
	 * Returns arguments for "log" command.
	 *
	 * @param IRepositoryCollectorPlugin[] $plugins Plugins.
	 *
	 * @return array
	 */
	private function _getLogCommandArguments(array $plugins)
	{
		$query_flags = $this->_getRevisionQueryFlags($plugins);

		$ret = array('-r', '{revision_range}', '--xml');

		if ( in_array(self::FLAG_VERBOSE, $query_flags) ) {
			$ret[] = '--verbose';
		}

		if ( in_array(self::FLAG_MERGE_HISTORY, $query_flags) ) {
			$ret[] = '--use-merge-history';
		}

		$ret[] = '{repository_url}';

		return $ret;
	}

	/**
	 * Returns revision query flags.
	 *
	 * @param IRepositoryCollectorPlugin[] $plugins Plugins.
	 *
	 * @return array
	 */
	private function _getRevisionQueryFlags(array $plugins)
	{
		$ret = array();

		foreach ( $plugins as $plugin ) {
			$ret = array_merge($ret, $plugin->getRevisionQueryFlags());
		}

		return array_unique($ret);
	}

	/**
	 * Parses output of "svn log" command.
	 *
	 * @param \SimpleXMLElement            $log     Log.
	 * @param IRepositoryCollectorPlugin[] $plugins Plugins.
	 *
	 * @return void
	 */
	private function _parseLog(\SimpleXMLElement $log, array $plugins)
	{
		foreach ( $plugins as $plugin ) {
			$plugin->parse($log);
		}
	}

	/**
	 * Displays plugin activity statistics.
	 *
	 * @return void
	 */
	private function _displayPluginActivityStatistics()
	{
		$statistics = array();

		// Combine statistics from all plugins.
		foreach ( $this->_plugins as $plugin ) {
			$statistics = array_merge($statistics, array_filter($plugin->getStatistics()));
		}

		// Show statistics.
		$this->_io->writeln('<debug>Combined Plugin Statistics:</debug>');

		foreach ( $statistics as $statistic_type => $occurrences ) {
			$this->_io->writeln('<debug> * ' . $statistic_type . ': ' . $occurrences . '</debug>');
		}
	}

	/**
	 * Registers a plugin.
	 *
	 * @param IPlugin $plugin Plugin.
	 *
	 * @return void
	 * @throws \LogicException When plugin is registered several times.
	 */
	public function registerPlugin(IPlugin $plugin)
	{
		$plugin_name = $plugin->getName();

		if ( $this->pluginRegistered($plugin_name) ) {
			throw new \LogicException('The "' . $plugin_name . '" revision log plugin is already registered.');
		}

		$plugin->setRevisionLog($this);
		$this->_plugins[$plugin_name] = $plugin;
	}

	/**
	 * Finds information using plugin.
	 *
	 * @param string       $plugin_name Plugin name.
	 * @param array|string $criteria    Search criteria.
	 *
	 * @return array
	 */
	public function find($plugin_name, $criteria)
	{
		return $this->getPlugin($plugin_name)->find((array)$criteria, $this->_projectPath);
	}

	/**
	 * Returns information about revisions.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param array  $revisions   Revisions.
	 *
	 * @return array
	 */
	public function getRevisionsData($plugin_name, array $revisions)
	{
		return $this->getPlugin($plugin_name)->getRevisionsData($revisions);
	}

	/**
	 * Determines if plugin is registered.
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return boolean
	 */
	public function pluginRegistered($plugin_name)
	{
		return array_key_exists($plugin_name, $this->_plugins);
	}

	/**
	 * Returns plugin instance.
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return IPlugin
	 * @throws \InvalidArgumentException When unknown plugin is given.
	 */
	public function getPlugin($plugin_name)
	{
		if ( !$this->pluginRegistered($plugin_name) ) {
			throw new \InvalidArgumentException('The "' . $plugin_name . '" revision log plugin is unknown.');
		}

		return $this->_plugins[$plugin_name];
	}

	/**
	 * Returns bugs, from revisions.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return array
	 */
	public function getBugsFromRevisions(array $revisions)
	{
		$bugs = array();
		$revisions_bugs = $this->getRevisionsData('bugs', $revisions);

		foreach ( $revisions as $revision ) {
			$revision_bugs = $revisions_bugs[$revision];

			foreach ( $revision_bugs as $bug_id ) {
				$bugs[$bug_id] = true;
			}
		}

		return array_keys($bugs);
	}

	/**
	 * Returns repository collector plugins.
	 *
	 * @param boolean $overwrite_mode Overwrite mode.
	 *
	 * @return IRepositoryCollectorPlugin[]
	 */
	protected function getRepositoryCollectorPlugins($overwrite_mode)
	{
		$plugins = $this->getPluginsByInterface(IRepositoryCollectorPlugin::class);

		if ( !$overwrite_mode ) {
			return $plugins;
		}

		return $this->getPluginsByInterface(IOverwriteAwarePlugin::class, $plugins);
	}

	/**
	 * Returns database collector plugins.
	 *
	 * @param boolean $overwrite_mode Overwrite mode.
	 *
	 * @return IDatabaseCollectorPlugin[]
	 */
	protected function getDatabaseCollectorPlugins($overwrite_mode)
	{
		$plugins = $this->getPluginsByInterface(IDatabaseCollectorPlugin::class);

		if ( !$overwrite_mode ) {
			return $plugins;
		}

		return $this->getPluginsByInterface(IOverwriteAwarePlugin::class, $plugins);
	}

	/**
	 * Returns plugin list filtered by interface.
	 *
	 * @param string    $interface Interface name.
	 * @param IPlugin[] $plugins   Plugins.
	 *
	 * @return IPlugin[]
	 */
	protected function getPluginsByInterface($interface, array $plugins = array())
	{
		if ( !$plugins ) {
			$plugins = $this->_plugins;
		}

		$ret = array();

		foreach ( $plugins as $plugin ) {
			if ( $plugin instanceof $interface ) {
				$ret[] = $plugin;
			}
		}

		return $ret;
	}

	/**
	 * Sets overwrite mode.
	 *
	 * @param IOverwriteAwarePlugin[] $plugins        Plugins.
	 * @param boolean                 $overwrite_mode Overwrite mode.
	 *
	 * @return void
	 */
	protected function setPluginsOverwriteMode(array $plugins, $overwrite_mode)
	{
		foreach ( $plugins as $plugin ) {
			$plugin->setOverwriteMode($overwrite_mode);
		}
	}

	/**
	 * Returns project path.
	 *
	 * @return string
	 */
	public function getProjectPath()
	{
		return $this->_projectPath;
	}

	/**
	 * Returns ref name.
	 *
	 * @return string
	 */
	public function getRefName()
	{
		return $this->_refName;
	}

}
