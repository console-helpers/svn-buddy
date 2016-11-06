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


class RefsPlugin extends AbstractDatabaseCollectorPlugin
{

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'refs';
	}

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes()
	{
		return array();
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
		// Do nothing, because "paths" plugin determines refs as well.
		$this->setLastRevision($to_revision);
		$this->advanceProgressBar();
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array       $criteria     Criteria.
	 * @param string|null $project_path Project path.
	 *
	 * @return array
	 */
	public function find(array $criteria, $project_path)
	{
		if ( !$criteria ) {
			return array();
		}

		$project_id = $this->getProject($project_path);

		if ( reset($criteria) === 'all_refs' ) {
			return $this->getProjectRefs($project_id);
		}
		elseif ( reset($criteria) === 'all' ) {
			return $this->revisionLog->find('paths', $project_path);
		}

		$ref_revisions = $this->findRevisionsByRef($project_id, $project_path, $criteria);

		sort($ref_revisions, SORT_NUMERIC);

		return $ref_revisions;
	}

	/**
	 * Returns names of all refs in a project.
	 *
	 * @param integer $project_id Project ID.
	 *
	 * @return array
	 */
	protected function getProjectRefs($project_id)
	{
		$sql = 'SELECT DISTINCT Name
				FROM ProjectRefs
				WHERE ProjectId = :project_id';

		return $this->database->fetchCol($sql, array('project_id' => $project_id));
	}

	/**
	 * Finds revisions by ref.
	 *
	 * @param integer $project_id   Project ID.
	 * @param string  $project_path Project path.
	 * @param array   $refs         Refs.
	 *
	 * @return array
	 */
	protected function findRevisionsByRef($project_id, $project_path, array $refs)
	{
		$ref_revisions = array();
		$ref_count = count($refs);

		foreach ( $refs as $ref ) {
			// 1. get all revisions by that path, which includes revisions from merged refs.
			$tmp_revisions = $this->revisionLog->find('paths', $project_path . $ref . '/');

			// 2. get revisions, that surely belong to needed ref.
			$sql = 'SELECT DISTINCT cr.Revision
					FROM ProjectRefs pr
					JOIN CommitRefs cr ON cr.RefId = pr.Id
					WHERE pr.ProjectId = :project_id AND pr.Name = :name';
			$all_ref_revisions = $this->database->fetchCol($sql, array('project_id' => $project_id, 'name' => $ref));

			// 3. filter out found revisions, that belongs to needed ref.
			$tmp_revisions = array_intersect($tmp_revisions, $all_ref_revisions);

			if ( $ref_count === 1 ) {
				return $tmp_revisions;
			}

			// Add revisions from refs.
			foreach ( $tmp_revisions as $revision ) {
				$ref_revisions[$revision] = true;
			}
		}

		return array_keys($ref_revisions);
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

		$sql = 'SELECT cr.Revision, pr.Name
				FROM CommitRefs cr
				JOIN ProjectRefs pr ON pr.Id = cr.RefId
				WHERE cr.Revision IN (:revisions)';
		$revisions_data = $this->database->fetchAll($sql, array('revisions' => $revisions));

		foreach ( $revisions_data as $revision_data ) {
			$revision = $revision_data['Revision'];
			$ref = $revision_data['Name'];

			if ( !isset($results[$revision]) ) {
				$results[$revision] = array();
			}

			$results[$revision][] = $ref;
		}

		return $this->addMissingResults($revisions, $results);
	}

}
