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


use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class RevisionLog
{

	const CACHE_FORMAT_VERSION = 1;

	/**
	 * Repository path.
	 *
	 * @var string
	 */
	private $_repositoryUrl;

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Installed plugins.
	 *
	 * @var IRevisionLogPlugin[]
	 */
	private $_plugins = array();

	/**
	 * Create revision log.
	 *
	 * @param string       $repository_url       Repository url.
	 * @param Connector    $repository_connector Repository connector.
	 * @param CacheManager $cache_manager        Cache.
	 * @param ConsoleIO    $io                   Console IO.
	 */
	public function __construct(
		$repository_url,
		Connector $repository_connector,
		CacheManager $cache_manager,
		ConsoleIO $io
	) {
		$this->_repositoryUrl = $repository_url;
		$this->_repositoryConnector = $repository_connector;
		$this->_cacheManager = $cache_manager;
		$this->_io = $io;
	}

	/**
	 * Queries missing revisions.
	 *
	 * @return void
	 * @throws \LogicException When no plugins are registered.
	 */
	public function refresh()
	{
		if ( !$this->_plugins ) {
			throw new \LogicException('Please register at least one revision log plugin.');
		}

		$project_url = $this->_getProjectUrl($this->_repositoryUrl);

		// Initialize plugins with data from cache.
		$cache_key = 'log:' . $project_url;
		$cache = $this->_cacheManager->getCache($cache_key, $this->_getCacheInvalidator());

		if ( is_array($cache) ) {
			foreach ( $this->_plugins as $plugin_name => $plugin ) {
				$plugin->setCollectedData($cache[$plugin_name]);
			}
		}

		$from_revision = $this->_getLastRevision();
		$to_revision = $this->_repositoryConnector->getLastRevision($project_url);

		if ( $to_revision > $from_revision ) {
			$this->_queryRevisionData($from_revision, $to_revision);

			// Collect and cache plugin data.
			$cache = array();

			foreach ( $this->_plugins as $plugin_name => $plugin ) {
				$cache[$plugin_name] = $plugin->getCollectedData();
			}

			$this->_cacheManager->setCache($cache_key, $cache, $this->_getCacheInvalidator());
		}
	}

	/**
	 * Returns project url (container for "trunk/branches/tags/releases" folders).
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return string
	 */
	private function _getProjectUrl($repository_url)
	{
		if ( preg_match('#^(.*?)/(trunk|branches|tags|releases).*$#', $repository_url, $regs) ) {
			return $regs[1];
		}

		return $repository_url;
	}

	/**
	 * Returns format version.
	 *
	 * @return mixed
	 */
	private function _getCacheInvalidator()
	{
		$invalidators = array('main' => 'main:' . self::CACHE_FORMAT_VERSION);

		foreach ( $this->_plugins as $plugin_name => $plugin ) {
			$invalidator_key = 'plugin(' . $plugin_name . ')';
			$invalidators[$invalidator_key] = $invalidator_key . ':' . $plugin->getCacheInvalidator();
		}

		ksort($invalidators);

		return implode(';', $invalidators);
	}

	/**
	 * Returns last known revision.
	 *
	 * @return integer
	 */
	private function _getLastRevision()
	{
		/** @var IRevisionLogPlugin $plugin */
		$plugin = reset($this->_plugins);
		$last_revision = $plugin->getLastRevision();

		if ( $last_revision === null ) {
			return $this->_repositoryConnector->getFirstRevision(
				$this->_getProjectUrl($this->_repositoryUrl)
			);
		}

		return $last_revision;
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
		$project_url = $this->_getProjectUrl($this->_repositoryUrl);

		$progress_bar = $this->_io->createProgressBar(ceil(($to_revision - $from_revision) / 1000));
		$progress_bar->setFormat(' * Reading missing revisions: %current%/%max% [%bar%] %percent:3s%%');
		$progress_bar->start();

		while ( $range_start < $to_revision ) {
			$range_end = min($range_start + 1000, $to_revision);

			$command = $this->_repositoryConnector->getCommand(
				'log',
				'-r ' . $range_start . ':' . $range_end . ' --xml --verbose {' . $project_url . '}'
			);

			$this->_parseLog($command->run());
			$range_start = $range_end + 1;

			$progress_bar->advance();
		}

		$progress_bar->finish();
		$this->_io->writeln('');
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
		foreach ( $this->_plugins as $plugin ) {
			$plugin->parse($log);
		}
	}

	/**
	 * Registers a plugin.
	 *
	 * @param IRevisionLogPlugin $plugin Plugin.
	 *
	 * @return void
	 * @throws \LogicException When plugin is registered several times.
	 */
	public function registerPlugin(IRevisionLogPlugin $plugin)
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

		return $this->_plugins[$plugin_name]->find((array)$criteria);
	}

	/**
	 * Returns information about revision.
	 *
	 * @param string  $plugin_name Plugin name.
	 * @param integer $revision    Revision.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When unknown plugin is given.
	 */
	public function getRevisionData($plugin_name, $revision)
	{
		if ( !$this->pluginRegistered($plugin_name) ) {
			throw new \InvalidArgumentException('The "' . $plugin_name . '" revision log plugin is unknown.');
		}

		return $this->_plugins[$plugin_name]->getRevisionData($revision);
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

		foreach ( $revisions as $revision ) {
			$revision_bugs = $this->getRevisionData('bugs', $revision);

			foreach ( $revision_bugs as $bug_id ) {
				$bugs[$bug_id] = true;
			}
		}

		return array_keys($bugs);
	}

}
