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

class RevisionLog
{

	/**
	 * Repository path.
	 *
	 * @var string
	 */
	private $_repositoryUrl;

	/**
	 * Repository connector.
	 *
	 * @var RepositoryConnector
	 */
	private $_repositoryConnector;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Log message parser.
	 *
	 * @var LogMessageParser
	 */
	private $_logMessageParser;

	/**
	 * Revisions.
	 *
	 * @var array
	 */
	private $_revisions = array();

	/**
	 * Revisions grouped by bugs.
	 *
	 * @var array
	 */
	private $_bugRevisions = array();

	/**
	 * Revisions affecting a specific path.
	 *
	 * @var array
	 */
	private $_pathRevisions = array();

	/**
	 * Create revision log.
	 *
	 * @param string              $repository_url       Repository url.
	 * @param RepositoryConnector $repository_connector Repository connector.
	 * @param CacheManager        $cache_manager        Cache.
	 * @param LogMessageParser    $log_message_parser   Log message parser.
	 */
	public function __construct(
		$repository_url,
		RepositoryConnector $repository_connector,
		CacheManager $cache_manager,
		LogMessageParser $log_message_parser
	) {
		$this->_repositoryUrl = $repository_url;
		$this->_repositoryConnector = $repository_connector;
		$this->_cacheManager = $cache_manager;
		$this->_logMessageParser = $log_message_parser;

		$this->_query();
	}

	/**
	 * Returns information about revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return array
	 */
	public function getRevisionData($revision)
	{
		if ( !isset($this->_revisions[$revision]) ) {
			throw new \InvalidArgumentException('Revision "' . $revision . '" not found');
		}

		return $this->_revisions[$revision];
	}

	/**
	 * Gets revisions, associated with a bug.
	 *
	 * @param string $bug_id Bug ID.
	 *
	 * @return array
	 */
	public function getRevisionsFromBug($bug_id)
	{
		return isset($this->_bugRevisions[$bug_id]) ? $this->_bugRevisions[$bug_id] : array();
	}

	/**
	 * Gets revisions, made at given path.
	 *
	 * @param string $path Path.
	 *
	 * @return array
	 */
	public function getRevisionsFromPath($path)
	{
		$path_revisions = array();
		$path_length = strlen($path);

		foreach ( $this->_pathRevisions as $test_path => $revisions ) {
			if ( substr($test_path, 0, $path_length) == $path ) {
				foreach ( $revisions as $revision ) {
					$path_revisions[$revision] = true;
				}
			}
		}

		$path_revisions = array_keys($path_revisions);
		sort($path_revisions, SORT_NUMERIC);

		return $path_revisions;
	}

	/**
	 * Queries missing revisions.
	 *
	 * @return void
	 */
	private function _query()
	{
		$project_url = $this->_getProjectUrl($this->_repositoryUrl);

		$cache_key = 'log:' . $project_url;
		$cache = $this->_cacheManager->getCache($cache_key);
		$this->_revisions = $cache['revisions'];
		$this->_bugRevisions = $cache['bug_revisions'];
		$this->_pathRevisions = $cache['path_revisions'];

		$from_revision = $this->_getLastRevision();
		$to_revision = $this->_repositoryConnector->getLastRevision($project_url);

		if ( $to_revision > $from_revision ) {
			$range_start = $from_revision;

			while ( $range_start < $to_revision ) {
				$range_end = min($range_start + 1000, $to_revision);

				$command = $this->_repositoryConnector->getCommand(
					'log',
					'-r ' . $range_start . ':' . $range_end . ' --xml --verbose {' . $project_url . '}'
				);

				$this->_parseLog($command->run());
				$range_start = $range_end + 1;
			}

			$this->_cacheManager->setCache($cache_key, array(
				'revisions' => $this->_revisions,
				'bug_revisions' => $this->_bugRevisions,
				'path_revisions' => $this->_pathRevisions,
			));
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
	 * Parses output of "svn log" command.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	private function _parseLog(\SimpleXMLElement $log)
	{
		// echo 'got ' . count($log->logentry) . ' revisions' . PHP_EOL;

		foreach ( $log->logentry as $logentry ) {
			$revision = (int)$logentry['revision'];

			$this->_revisions[$revision] = array(
				'author' => (string)$logentry->author,
				'date' => strtotime($logentry->date),
				'msg' => (string)$logentry->msg,
				'paths' => $this->_processPaths($logentry),
				'bugs' => $this->_processBugs($logentry),
			);
		}
	}

	/**
	 * Processes revision paths.
	 *
	 * @param \SimpleXMLElement $logentry The "logentry" node.
	 *
	 * @return array
	 */
	private function _processPaths(\SimpleXMLElement $logentry)
	{
		$paths = array();
		$revision = (int)$logentry['revision'];

		foreach ( $logentry->paths->path as $path_node ) {
			/** @var \SimpleXMLElement $path_node */
			$path = (string)$path_node;

			if ( !isset($this->_pathRevisions[$path]) ) {
				$this->_pathRevisions[$path] = array();
			}

			$this->_pathRevisions[$path][] = $revision;

			$path_data = array('path' => $path);

			foreach ( $path_node->attributes() as $attribute_name => $attribute_value ) {
				$path_data[$attribute_name] = (string)$attribute_value;
			}

			$paths[] = $path_data;
		}

		return $paths;
	}

	/**
	 * Processes revision bugs.
	 *
	 * @param \SimpleXMLElement $logentry The "logentry" node.
	 *
	 * @return array
	 */
	private function _processBugs(\SimpleXMLElement $logentry)
	{
		$revision = (int)$logentry['revision'];
		$bugs = $this->_logMessageParser->parse((string)$logentry->msg);

		foreach ( $bugs as $bug_id ) {
			if ( !isset($this->_bugRevisions[$bug_id]) ) {
				$this->_bugRevisions[$bug_id] = array();
			}

			$this->_bugRevisions[$bug_id][] = $revision;
		}

		return $bugs;
	}

	/**
	 * Returns last known revision.
	 *
	 * @return integer
	 */
	private function _getLastRevision()
	{
		if ( !$this->_revisions ) {
			return $this->_repositoryConnector->getFirstRevision(
				$this->_getProjectUrl($this->_repositoryUrl)
			);
		}

		end($this->_revisions);

		return key($this->_revisions);
	}

}
