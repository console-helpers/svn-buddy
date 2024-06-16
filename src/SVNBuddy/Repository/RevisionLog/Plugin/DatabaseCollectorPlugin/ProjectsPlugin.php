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


class ProjectsPlugin extends AbstractDatabaseCollectorPlugin
{

	const STATISTIC_PROJECT_DELETED = 'project_deleted';

	const STATISTIC_PROJECT_RESTORED = 'project_restored';

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'projects';
	}

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes()
	{
		return array(
			self::STATISTIC_PROJECT_DELETED,
			self::STATISTIC_PROJECT_RESTORED,
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
		$projects = $this->getProjects();
		$this->setLastRevision($to_revision);

		// When no projects exists and there 20+ commits, then consider repository
		// having single project without known structure (trunk/branches/tags) only.
		if ( !$projects && $to_revision >= 20 ) {
			$this->createRepositoryWideProject();
		}

		foreach ( $projects as $project_data ) {
			$is_deleted = $project_data['IsDeleted'];

			if ( $is_deleted && !is_numeric($project_data['RevisionDeleted']) ) {
				$this->repositoryFiller->setProjectStatus($project_data['Id'], 0);
				$this->recordStatistic(self::STATISTIC_PROJECT_RESTORED);
			}
			elseif ( !$is_deleted && is_numeric($project_data['RevisionDeleted']) ) {
				$this->repositoryFiller->setProjectStatus($project_data['Id'], 1);
				$this->recordStatistic(self::STATISTIC_PROJECT_DELETED);
			}
		}

		$this->advanceProgressBar();
	}

	/**
	 * Creates one project per repository and moves all commits into it.
	 *
	 * @return void
	 */
	protected function createRepositoryWideProject()
	{
		$select_sql = 'SELECT Id FROM Paths WHERE ProjectPath = :project_path LIMIT 100';

		while ( true ) {
			$path_ids = $this->database->fetchCol($select_sql, array('project_path' => ''));

			if ( !$path_ids ) {
				break;
			}

			$this->repositoryFiller->movePathsIntoProject($path_ids, '/');
		}

		$project_id = $this->repositoryFiller->addProject('/');

		$sql = 'SELECT Revision
				FROM Commits';
		$all_commits = $this->database->yieldCol($sql);

		foreach ( $all_commits as $revision ) {
			$this->repositoryFiller->addCommitToProject($revision, $project_id);
		}
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array  $criteria     Criteria.
	 * @param string $project_path Project path.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When some of given project paths are not found.
	 */
	public function find(array $criteria, $project_path)
	{
		if ( !$criteria ) {
			return array();
		}

		$sql = 'SELECT Path, Id
				FROM Projects
				WHERE Path IN (:paths)';
		$projects = $this->database->fetchPairs($sql, array('paths' => $criteria));

		$missing_projects = array_diff($criteria, array_keys($projects));

		if ( $missing_projects ) {
			throw new \InvalidArgumentException(sprintf(
				'The "%s" project(-s) not found by "%s" plugin.',
				implode('", "', $missing_projects),
				$this->getName()
			));
		}

		$sql = 'SELECT DISTINCT Revision
				FROM CommitProjects
				WHERE ProjectId IN (:project_ids)';
		$project_revisions = $this->database->fetchCol($sql, array('project_ids' => $projects));

		sort($project_revisions, SORT_NUMERIC);

		return $project_revisions;
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
		$all_projects = $this->getAllProjects();

		$sql = 'SELECT Revision, ProjectId
				FROM CommitProjects
				WHERE Revision IN (:revisions)';
		$revisions_data = $this->database->fetchAll($sql, array('revisions' => $revisions));

		foreach ( $revisions_data as $revision_data ) {
			$revision = $revision_data['Revision'];
			$project_path = $all_projects[$revision_data['ProjectId']];

			if ( !isset($results[$revision]) ) {
				$results[$revision] = array();
			}

			$results[$revision][] = $project_path;
		}

		return $this->addMissingResults($revisions, $results);
	}

	/**
	 * Returns all projects.
	 *
	 * @return array
	 */
	protected function getAllProjects()
	{
		$sql = 'SELECT Id, Path
				FROM Projects';

		return $this->database->fetchPairs($sql);
	}

	/**
	 * Returns project meta information.
	 *
	 * @param string $project_path Project path.
	 *
	 * @return array
	 */
	public function getMeta($project_path)
	{
		$projects = $this->getProjects('Id = :id', array('id' => $this->getProject($project_path)));

		return reset($projects);
	}

}
