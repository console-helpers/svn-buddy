<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RepositoryCollectorPlugin;


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\TOverwriteAwarePlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;

class MergesPlugin extends AbstractRepositoryCollectorPlugin implements IOverwriteAwarePlugin
{

	use TOverwriteAwarePlugin;

	const STATISTIC_MERGE_ADDED = 'merge_added';

	const STATISTIC_MERGE_DELETED = 'merge_deleted';

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'merges';
	}

	/**
	 * Returns revision query flags.
	 *
	 * @return array
	 */
	public function getRevisionQueryFlags()
	{
		return array(RevisionLog::FLAG_MERGE_HISTORY);
	}

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes()
	{
		return array(
			self::STATISTIC_MERGE_ADDED, self::STATISTIC_MERGE_DELETED,
		);
	}

	/**
	 * Does actual parsing.
	 *
	 * @param integer           $revision  Revision.
	 * @param \SimpleXMLElement $log_entry Log Entry.
	 *
	 * @return void
	 */
	protected function doParse($revision, \SimpleXMLElement $log_entry)
	{
		$merged_revisions = array();

		foreach ( $log_entry->logentry as $merged_log_entry ) {
			$merged_revisions[] = (int)$merged_log_entry['revision'];
		}

		$this->repositoryFiller->addMergeCommit($revision, $merged_revisions);
		$this->recordStatistic(self::STATISTIC_MERGE_ADDED, count($merged_revisions));
	}

	/**
	 * @inheritDoc
	 */
	protected function remove($revision)
	{
		$merged_revisions_count = $this->repositoryFiller->removeMergeCommit($revision);
		$this->recordStatistic(self::STATISTIC_MERGE_DELETED, $merged_revisions_count);
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array       $criteria     Criteria.
	 * @param string|null $project_path Project path.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When one of given merge revision wasn't found.
	 */
	public function find(array $criteria, $project_path)
	{
		if ( !$criteria ) {
			return array();
		}

		$first_criteria = reset($criteria);
		$project_id = $this->getProject($project_path);

		if ( $first_criteria === 'all_merges' ) {
			$sql = 'SELECT DISTINCT m.MergeRevision
					FROM Merges m
					JOIN CommitProjects cp ON cp.Revision = m.MergeRevision
					WHERE cp.ProjectId = :project_id';

			return $this->database->fetchCol($sql, array('project_id' => $project_id));
		}
		elseif ( $first_criteria === 'all_merged' ) {
			$sql = 'SELECT DISTINCT m.MergedRevision
					FROM Merges m
					JOIN CommitProjects cp ON cp.Revision = m.MergedRevision
					WHERE cp.ProjectId = :project_id';

			return $this->database->fetchCol($sql, array('project_id' => $project_id));
		}

		$merge_revisions = array();
		$merged_revisions = array();

		$sql = 'SELECT m.MergeRevision, m.MergedRevision
				FROM Merges m
				JOIN CommitProjects cp ON cp.Revision = m.MergeRevision
				WHERE cp.ProjectId = :project_id AND m.MergeRevision IN (:merge_revisions)';
		$tmp_revisions = $this->database->fetchAll($sql, array(
			'project_id' => $project_id,
			'merge_revisions' => $criteria,
		));

		foreach ( $tmp_revisions as $revision_data ) {
			$merge_revisions[$revision_data['MergeRevision']] = true;
			$merged_revisions[$revision_data['MergedRevision']] = true;
		}

		$unknown_merge_revisions = array_diff($criteria, array_keys($merge_revisions));

		if ( $unknown_merge_revisions ) {
			throw new \InvalidArgumentException(
				'The merge revision(-s) "' . implode('", "', $unknown_merge_revisions) . '" not found.'
			);
		}

		$merged_revisions = array_keys($merged_revisions);
		sort($merged_revisions, SORT_NUMERIC);

		return $merged_revisions;
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

		$sql = 'SELECT MergeRevision, MergedRevision
				FROM Merges
				WHERE MergedRevision IN (:merged_revisions)';
		$revisions_data = $this->getRawRevisionsData($sql, 'merged_revisions', $revisions);

		foreach ( $revisions_data as $revision_data ) {
			$merge_revision = $revision_data['MergeRevision'];
			$merged_revision = $revision_data['MergedRevision'];

			if ( !isset($results[$merged_revision]) ) {
				$results[$merged_revision] = array();
			}

			$results[$merged_revision][] = $merge_revision;
		}

		return $this->addMissingResults($revisions, $results);
	}

}
