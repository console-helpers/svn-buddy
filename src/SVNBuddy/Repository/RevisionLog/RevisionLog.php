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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IDatabaseCollectorPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IRepositoryCollectorPlugin;

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
	 * Create revision log.
	 *
	 * @param string    $repository_url       Repository url.
	 * @param Connector $repository_connector Repository connector.
	 * @param ConsoleIO $io                   Console IO.
	 */
	public function __construct(
		$repository_url,
		Connector $repository_connector,
		ConsoleIO $io = null
	) {
		$this->_io = $io;
		$this->_repositoryConnector = $repository_connector;

		$this->_repositoryRootUrl = $repository_connector->getRootUrl($repository_url);

		$relative_path = $repository_connector->getRelativePath($repository_url);
		$this->_projectPath = $repository_connector->getProjectUrl($relative_path) . '/';
		$this->_refName = $repository_connector->getRefByPath($relative_path);
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
			$to_revision = $this->_repositoryConnector->getLastRevision($this->_repositoryRootUrl);
		}

		if ( $to_revision > $from_revision ) {
			$this->_queryRevisionData($from_revision, $to_revision);
		}
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
	 *
	 * @return void
	 */
	private function _queryRevisionData($from_revision, $to_revision)
	{
		$range_start = $from_revision;

		// The "io" isn't set during autocomplete.
		if ( isset($this->_io) ) {
			// Create progress bar for repository plugins, where data amount is known upfront.
			$progress_bar = $this->_io->createProgressBar(ceil(($to_revision - $from_revision) / 200) + 1);
			$progress_bar->setMessage(' * Reading missing revisions:');
			$progress_bar->setFormat(
				'%message% %current%/%max% [%bar%] <info>%percent:3s%%</info> %elapsed:6s%/%estimated:-6s% <info>%memory:-10s%</info>'
			);
			$progress_bar->start();
		}

		$log_command_arguments = $this->_getLogCommandArguments();
		$is_verbose = isset($this->_io) && $this->_io->isVerbose();

		while ( $range_start <= $to_revision ) {
			$range_end = min($range_start + 199, $to_revision);

			$command = $this->_repositoryConnector->getCommand(
				'log',
				sprintf($log_command_arguments, $range_start, $range_end, $this->_repositoryRootUrl)
			);
			$command->setCacheDuration('10 years');
			$svn_log = $command->run();

			$this->_parseLog($svn_log);

			$range_start = $range_end + 1;

			if ( isset($progress_bar) ) {
				$progress_bar->advance();
			}
		}

		if ( isset($progress_bar) ) {
			// Remove progress bar of repository plugins.
			$progress_bar->clear();
			unset($progress_bar);

			// Create progress bar for database plugins, where data amount isn't known upfront.
			$progress_bar = $this->_io->createProgressBar();
			$progress_bar->setMessage(' * Reading missing revisions:');
			$progress_bar->setFormat('%message% %current% [%bar%] %elapsed:6s% <info>%memory:-10s%</info>');
			$progress_bar->start();

			foreach ( $this->getDatabaseCollectorPlugins() as $plugin ) {
				$plugin->process($from_revision, $to_revision, $progress_bar);
			}
		}
		else {
			foreach ( $this->getDatabaseCollectorPlugins() as $plugin ) {
				$plugin->process($from_revision, $to_revision);
			}
		}

		if ( isset($progress_bar) ) {
			$progress_bar->finish();
			$this->_io->writeln('');
		}

		if ( $is_verbose ) {
			$this->_displayPluginActivityStatistics();
		}
	}

	/**
	 * Returns arguments for "log" command.
	 *
	 * @return string
	 */
	private function _getLogCommandArguments()
	{
		$query_flags = $this->_getRevisionQueryFlags();

		$ret = '-r %d:%d --xml';

		if ( in_array(self::FLAG_VERBOSE, $query_flags) ) {
			$ret .= ' --verbose';
		}

		if ( in_array(self::FLAG_MERGE_HISTORY, $query_flags) ) {
			$ret .= ' --use-merge-history';
		}

		$ret .= ' {%s}';

		return $ret;
	}

	/**
	 * Returns revision query flags.
	 *
	 * @return array
	 */
	private function _getRevisionQueryFlags()
	{
		$ret = array();

		foreach ( $this->getRepositoryCollectorPlugins() as $plugin ) {
			$ret = array_merge($ret, $plugin->getRevisionQueryFlags());
		}

		return array_unique($ret);
	}

	/**
	 * Parses output of "svn log" command.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	private function _parseLog(\SimpleXMLElement $log)
	{
		foreach ( $this->getRepositoryCollectorPlugins() as $plugin ) {
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

		$this->_plugins[$plugin_name] = $plugin;
	}

	/**
	 * Finds information using plugin.
	 *
	 * @param string       $plugin_name Plugin name.
	 * @param array|string $criteria    Search criteria.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When unknown plugin is given.
	 */
	public function find($plugin_name, $criteria)
	{
		if ( !$this->pluginRegistered($plugin_name) ) {
			throw new \InvalidArgumentException('The "' . $plugin_name . '" revision log plugin is unknown.');
		}

		return $this->_plugins[$plugin_name]->find((array)$criteria, $this->_projectPath);
	}

	/**
	 * Returns information about revisions.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param array  $revisions   Revisions.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When unknown plugin is given.
	 */
	public function getRevisionsData($plugin_name, array $revisions)
	{
		if ( !$this->pluginRegistered($plugin_name) ) {
			throw new \InvalidArgumentException('The "' . $plugin_name . '" revision log plugin is unknown.');
		}

		return $this->_plugins[$plugin_name]->getRevisionsData($revisions);
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
	 * @return IRepositoryCollectorPlugin[]
	 */
	protected function getRepositoryCollectorPlugins()
	{
		return $this->getPluginsByInterface(
			'ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IRepositoryCollectorPlugin'
		);
	}

	/**
	 * Returns database collector plugins.
	 *
	 * @return IDatabaseCollectorPlugin[]
	 */
	protected function getDatabaseCollectorPlugins()
	{
		return $this->getPluginsByInterface(
			'ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IDatabaseCollectorPlugin'
		);
	}

	/**
	 * Returns plugin list filtered by interface.
	 *
	 * @param string $interface Interface name.
	 *
	 * @return IPlugin[]
	 */
	protected function getPluginsByInterface($interface)
	{
		$ret = array();

		foreach ( $this->_plugins as $plugin ) {
			if ( $plugin instanceof $interface ) {
				$ret[] = $plugin;
			}
		}

		return $ret;
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
