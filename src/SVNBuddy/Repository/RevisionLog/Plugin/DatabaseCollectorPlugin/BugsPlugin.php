<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\DatabaseCollectorPlugin;


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\TOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RepositoryFiller;

class BugsPlugin extends AbstractDatabaseCollectorPlugin implements IOverwriteAwarePlugin
{

	use TOverwriteAwarePlugin;

	const STATISTIC_BUG_ADDED_TO_COMMIT = 'bug_added_to_commit';

	const STATISTIC_BUG_REMOVED_FROM_COMMIT = 'bug_removed_from_commit';

	/**
	 * Repository url.
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
	 * Log message parser factory.
	 *
	 * @var LogMessageParserFactory
	 */
	private $_logMessageParserFactory;

	/**
	 * Create bugs revision log plugin.
	 *
	 * @param ExtendedPdoInterface    $database                   Database.
	 * @param RepositoryFiller        $repository_filler          Repository filler.
	 * @param string                  $repository_url             Repository url.
	 * @param Connector               $repository_connector       Repository connector.
	 * @param LogMessageParserFactory $log_message_parser_factory Log message parser.
	 */
	public function __construct(
		ExtendedPdoInterface $database,
		RepositoryFiller $repository_filler,
		$repository_url,
		Connector $repository_connector,
		LogMessageParserFactory $log_message_parser_factory
	) {
		parent::__construct($database, $repository_filler);

		$this->_repositoryUrl = $repository_url;
		$this->_repositoryConnector = $repository_connector;
		$this->_logMessageParserFactory = $log_message_parser_factory;
	}

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'bugs';
	}

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes()
	{
		return array(
			self::STATISTIC_BUG_ADDED_TO_COMMIT, self::STATISTIC_BUG_REMOVED_FROM_COMMIT,
		);
	}

	/**
	 * Processes data.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 *
	 * @return void
	 */
	public function doProcess($from_revision, $to_revision)
	{
		$this->populateMissingBugRegExp();

		$last_revision = $this->getLastRevision();

		if ( $this->isOverwriteMode() ) {
			$this->remove($from_revision, $to_revision);
			$this->detectBugs($from_revision, $to_revision);
		}
		elseif ( $to_revision > $last_revision ) {
			$this->detectBugs($last_revision + 1, $to_revision);
		}

		if ( $to_revision > $last_revision ) {
			$this->setLastRevision($to_revision);
		}
	}

	/**
	 * Removes changes plugin made based on a given revision.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 *
	 * @return void
	 */
	protected function remove($from_revision, $to_revision)
	{
		for ( $revision = $from_revision; $revision <= $to_revision; $revision++ ) {
			$bug_count = $this->repositoryFiller->removeBugsFromCommit($revision);
			$this->recordStatistic(self::STATISTIC_BUG_REMOVED_FROM_COMMIT, $bug_count);
		}
	}

	/**
	 * Populate "BugRegExp" column for projects without it.
	 *
	 * @param boolean $cache_overwrite Overwrite used "bugtraq:logregex" SVN property's cached value.
	 *
	 * @return void
	 */
	protected function populateMissingBugRegExp($cache_overwrite = false)
	{
		$projects = $this->getProjects('BugRegExp IS NULL');

		if ( !$projects ) {
			$this->advanceProgressBar();

			return;
		}

		foreach ( $projects as $project_data ) {
			$bug_regexp = $this->detectProjectBugTraqRegEx(
				$project_data['Path'],
				$project_data['RevisionLastSeen'],
				(bool)$project_data['IsDeleted'],
				$cache_overwrite
			);

			$this->repositoryFiller->setProjectBugRegexp($project_data['Id'], $bug_regexp);
			$this->advanceProgressBar();
		}
	}

	/**
	 * Determines project bug tracking regular expression.
	 *
	 * @param string  $project_path    Project project_path.
	 * @param integer $revision        Revision.
	 * @param boolean $project_deleted Project is deleted.
	 * @param boolean $cache_overwrite Overwrite used "bugtraq:logregex" SVN property's cached value.
	 *
	 * @return string
	 */
	protected function detectProjectBugTraqRegEx($project_path, $revision, $project_deleted, $cache_overwrite = false)
	{
		$ref_paths = $this->getLastChangedRefPaths($project_path);

		if ( !$ref_paths ) {
			return '';
		}

		foreach ( $ref_paths as $ref_path ) {
			$logregex = $this->_repositoryConnector
				->withCacheDuration('1 year')
				->withCacheOverwrite($cache_overwrite)
				->getProperty(
					'bugtraq:logregex',
					$this->_repositoryUrl . $ref_path . ($project_deleted ? '@' . $revision : '')
				);

			if ( strlen($logregex) ) {
				return $logregex;
			}
		}

		return '';
	}

	/**
	 * Returns given project refs, where last changed are on top.
	 *
	 * @param string $project_path Path.
	 *
	 * @return array
	 */
	protected function getLastChangedRefPaths($project_path)
	{
		$own_nesting_level = substr_count($project_path, '/') - 1;

		$where_clause = array(
			'Path LIKE :parent_path',
			'PathNestingLevel BETWEEN :from_level AND :to_level',
			'RevisionDeleted IS NULL',
		);

		$sql = 'SELECT Path, RevisionLastSeen
				FROM Paths
				WHERE (' . implode(') AND (', $where_clause) . ')';
		$paths = $this->database->fetchPairs($sql, array(
			'parent_path' => $project_path . '%',
			'from_level' => $own_nesting_level + 1,
			'to_level' => $own_nesting_level + 2,
		));

		// No sub-folders.
		if ( !$paths ) {
			return array();
		}

		$filtered_paths = array();

		foreach ( $paths as $path => $revision ) {
			if ( $this->isRef($path) ) {
				$filtered_paths[$path] = $revision;
			}
		}

		// None of sub-folders matches a ref.
		if ( !$filtered_paths ) {
			return array();
		}

		arsort($filtered_paths, SORT_NUMERIC);

		return array_keys($filtered_paths);
	}

	/**
	 * Detects if given project_path is known project root.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	protected function isRef($path)
	{
		// Not a folder.
		if ( substr($path, -1, 1) !== '/' ) {
			return false;
		}

		return $this->_repositoryConnector->isRefRoot($path);
	}

	/**
	 * Detects bugs, associated with each commit from a given revision range.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 *
	 * @return void
	 */
	protected function detectBugs($from_revision, $to_revision)
	{
		$bug_regexp_mapping = $this->getProjectBugRegExps();

		if ( !$bug_regexp_mapping ) {
			$this->advanceProgressBar();

			return;
		}

		$range_start = $from_revision;

		while ( $range_start <= $to_revision ) {
			$range_end = min($range_start + 999, $to_revision);

			$this->doDetectBugs($range_start, $range_end, $bug_regexp_mapping);
			$this->advanceProgressBar();

			$range_start = $range_end + 1;
		}
	}

	/**
	 * Returns "BugRegExp" field associated with every project.
	 *
	 * @return array
	 */
	protected function getProjectBugRegExps()
	{
		$projects = $this->getProjects("BugRegExp != ''");

		if ( !$projects ) {
			return array();
		}

		$ret = array();

		foreach ( $projects as $project_data ) {
			$ret[$project_data['Id']] = $project_data['BugRegExp'];
		}

		return $ret;
	}

	/**
	 * Detects bugs, associated with each commit from a given revision range.
	 *
	 * @param integer $from_revision      From revision.
	 * @param integer $to_revision        To revision.
	 * @param array   $bug_regexp_mapping Mapping between project and it's "BugRegExp" field.
	 *
	 * @return void
	 */
	protected function doDetectBugs($from_revision, $to_revision, array $bug_regexp_mapping)
	{
		$commits_by_project = $this->getCommitsGroupedByProject($from_revision, $to_revision);

		foreach ( $commits_by_project as $project_id => $project_commits ) {
			if ( !isset($bug_regexp_mapping[$project_id]) ) {
				continue;
			}

			$log_message_parser = $this->_logMessageParserFactory->getLogMessageParser(
				$bug_regexp_mapping[$project_id]
			);

			foreach ( $project_commits as $revision => $log_message ) {
				$bugs = $log_message_parser->parse($log_message);

				if ( $bugs ) {
					$this->repositoryFiller->addBugsToCommit($bugs, $revision);
					$this->recordStatistic(self::STATISTIC_BUG_ADDED_TO_COMMIT, count($bugs));
				}
			}
		}
	}

	/**
	 * Returns commits grouped by project.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 *
	 * @return array
	 */
	protected function getCommitsGroupedByProject($from_revision, $to_revision)
	{
		$sql = 'SELECT cp.Revision, c.Message, cp.ProjectId
				FROM CommitProjects cp
				JOIN Commits c ON c.Revision = cp.Revision
				WHERE cp.Revision BETWEEN :from_revision AND :to_revision';
		$commits = $this->database->yieldAll($sql, array(
			'from_revision' => $from_revision,
			'to_revision' => $to_revision,
		));

		$ret = array();
		$processed_revisions = array();

		foreach ( $commits as $commit_data ) {
			$revision = $commit_data['Revision'];

			// Don't process revision more then once (e.g. when commit belongs to different projects).
			if ( isset($processed_revisions[$revision]) ) {
				continue;
			}

			$project_id = $commit_data['ProjectId'];

			if ( !isset($ret[$project_id]) ) {
				$ret[$project_id] = array();
			}

			$ret[$project_id][$revision] = $commit_data['Message'];
			$processed_revisions[$revision] = true;
		}

		return $ret;
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array  $criteria     Criteria.
	 * @param string $project_path Project path.
	 *
	 * @return array
	 */
	public function find(array $criteria, $project_path)
	{
		if ( !$criteria ) {
			return array();
		}

		$project_id = $this->getProject($project_path);

		$sql = 'SELECT DISTINCT cb.Revision
				FROM CommitBugs cb
				JOIN CommitProjects cp ON cp.Revision = cb.Revision
				WHERE cp.ProjectId = :project_id AND cb.Bug IN (:bugs)';
		$bug_revisions = $this->database->fetchCol($sql, array('project_id' => $project_id, 'bugs' => $criteria));

		sort($bug_revisions, SORT_NUMERIC);

		return $bug_revisions;
	}

	/**
	 * Returns information about revisions.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return array
	 */
	public function getRevisionsData(array $revisions)
	{
		$results = array();

		$sql = 'SELECT Revision, Bug
				FROM CommitBugs
				WHERE Revision IN (:revisions)';
		$revisions_data = $this->getRawRevisionsData($sql, 'revisions', $revisions);

		foreach ( $revisions_data as $revision_data ) {
			$revision = $revision_data['Revision'];
			$bug = $revision_data['Bug'];

			if ( !isset($results[$revision]) ) {
				$results[$revision] = array();
			}

			$results[$revision][] = $bug;
		}

		return $this->addMissingResults($revisions, $results);
	}

	/**
	 * Refreshes BugRegExp of a project.
	 *
	 * @param string $project_path Project path.
	 *
	 * @return void
	 */
	public function refreshBugRegExp($project_path)
	{
		$project_id = $this->getProject($project_path);

		$sql = 'UPDATE Projects
				SET BugRegExp = NULL
				WHERE Id = :project_id';
		$this->database->perform($sql, array(
			'project_id' => $project_id,
		));

		$this->populateMissingBugRegExp(true);
	}

}
