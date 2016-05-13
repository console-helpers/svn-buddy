<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin;


class SummaryPlugin extends AbstractRepositoryCollectorPlugin
{

	const STATISTIC_COMMIT_ADDED = 'commit_added';

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'summary';
	}

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes()
	{
		return array(
			self::STATISTIC_COMMIT_ADDED,
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
		$this->repositoryFiller->addCommit(
			$revision,
			(string)$log_entry->author,
			strtotime($log_entry->date),
			(string)$log_entry->msg
		);

		$this->recordStatistic(self::STATISTIC_COMMIT_ADDED);
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array       $criteria     Criteria.
	 * @param string|null $project_path Project path.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When malformed criterion given (e.g. no field name).
	 */
	public function find(array $criteria, $project_path)
	{
		if ( !$criteria ) {
			return array();
		}

		$summary_revisions = array();
		$project_id = $this->getProject($project_path);

		foreach ( $criteria as $criterion ) {
			if ( strpos($criterion, ':') === false ) {
				$error_msg = 'Each criterion of "%s" plugin must be in "%s" format.';
				throw new \InvalidArgumentException(sprintf($error_msg, $this->getName(), 'field:value'));
			}

			list ($field, $value) = explode(':', $criterion, 2);

			if ( $field === 'author' ) {
				$sql = 'SELECT c.Revision
						FROM Commits c
						JOIN CommitProjects cp ON cp.Revision = c.Revision
						WHERE cp.ProjectId = :project_id AND c.Author = :author';
				$tmp_revisions = $this->database->fetchCol($sql, array(
					'project_id' => $project_id,
					'author' => $value,
				));

				foreach ( $tmp_revisions as $revision ) {
					$summary_revisions[$revision] = true;
				}
			}
			else {
				$error_msg = 'Searching by "%s" is not supported by "%s" plugin.';
				throw new \InvalidArgumentException(sprintf($error_msg, $field, $this->getName()));
			}
		}

		$summary_revisions = array_keys($summary_revisions);
		sort($summary_revisions, SORT_NUMERIC);

		return $summary_revisions;
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
		$sql = 'SELECT Revision, Author AS author, Date AS date, Message AS msg
				FROM Commits
				WHERE Revision IN (:revision_ids)';
		$results = $this->database->fetchAssoc($sql, array('revision_ids' => $revisions));

		foreach ( array_keys($results) as $revision ) {
			unset($results[$revision]['Revision']);
		}

		$this->assertNoMissingRevisions($revisions, $results);

		return $results;
	}

}
