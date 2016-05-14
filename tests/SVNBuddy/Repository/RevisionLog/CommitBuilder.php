<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RepositoryFiller;

class CommitBuilder
{

	/**
	 * Paths map (key - path; value - path id).
	 *
	 * @var array
	 */
	private $_pathsMap = array();

	/**
	 * Projects map (key - project path; value - project id).
	 *
	 * @var array
	 */
	private $_projectsMap = array();

	/**
	 * Refs map (key - project id + ref name; value - ref id).
	 *
	 * @var array
	 */
	private $_refsMap = array();

	/**
	 * Repository filler.
	 *
	 * @var RepositoryFiller
	 */
	private $_repositoryFiller;

	/**
	 * Database cache.
	 *
	 * @var DatabaseCache
	 */
	private $_databaseCache;

	/**
	 * Commit data.
	 *
	 * @var Commit[]
	 */
	private $_commits = array();

	/**
	 * Creates commit builder.
	 *
	 * @param RepositoryFiller $repository_filler Repository filler.
	 * @param DatabaseCache    $database_cache    Database cache.
	 */
	public function __construct(RepositoryFiller $repository_filler, DatabaseCache $database_cache)
	{
		$this->_repositoryFiller = $repository_filler;
		$this->_databaseCache = $database_cache;

		$this->_databaseCache->cacheTable('Paths');
	}

	/**
	 * Adds commit.
	 *
	 * @param integer $revision Revision.
	 * @param string  $author   Author.
	 * @param integer $date     Date.
	 * @param string  $message  Message.
	 *
	 * @return Commit
	 */
	public function addCommit($revision, $author, $date, $message)
	{
		$commit = new Commit($revision, $author, $date, $message);
		$this->_commits[] = $commit;

		return $commit;
	}

	/**
	 * Builds the commits. Will remove added commits after finished.
	 *
	 * @return void
	 */
	public function build()
	{
		$commit_projects = array();
		$commit_refs = array();

		foreach ( $this->_commits as $commit ) {
			$commit_data = $commit->getCommitData();
			$revision = $commit_data['revision'];

			$commit_projects[$revision] = array();
			$commit_refs[$revision] = array();

			$this->_repositoryFiller->addCommit(
				$revision,
				$commit_data['author'],
				$commit_data['date'],
				$commit_data['message']
			);

			$this->_repositoryFiller->addBugsToCommit($commit->getBugs(), $revision);
			$this->_repositoryFiller->addMergeCommit($revision, $commit->getMergedCommits());

			foreach ( $commit->getPaths() as $path => $path_data ) {
				$ref_name = $path_data['ref_name'];
				$project_path = $path_data['project_path'];

				// Create missing path.
				if ( !isset($this->_pathsMap[$path]) ) {
					$this->_pathsMap[$path] = $this->_repositoryFiller->addPath(
						$path,
						$ref_name,
						$project_path,
						$revision
					);
				}
				else {
					$sql = 'SELECT RevisionAdded, RevisionDeleted, RevisionLastSeen
							FROM Paths
							WHERE Id = :id';
					$touch_path_data = $this->_databaseCache->getFromCache(
						'Paths',
						$this->_repositoryFiller->getPathChecksum($path) . '/' . __METHOD__,
						$sql,
						array('id' => $this->_pathsMap[$path])
					);

					$touched_paths = $this->_repositoryFiller->touchPath(
						$path,
						$revision,
						$this->_repositoryFiller->getPathTouchFields($path_data['action'], $revision, $touch_path_data)
					);

					foreach ( $touched_paths as $touched_path_hash => $touched_path_fields_hash ) {
						if ( $this->_databaseCache->getFromCache('Paths', $touched_path_hash . '/' . __METHOD__) !== false ) {
							$this->_databaseCache->setIntoCache('Paths', $touched_path_hash . '/' . __METHOD__, $touched_path_fields_hash);
						}
					}
				}

				$copy_from_path = $path_data['copy_from_path'];
				$copy_from_revision = $path_data['copy_from_revision'];

				if ( $copy_from_path ) {
					$copy_from_path_id = $this->_repositoryFiller->addPath(
						$copy_from_path,
						'',
						'',
						$copy_from_revision
					);
				}

				// Add path to commit.
				$this->_repositoryFiller->addPathToCommit(
					$revision,
					$path_data['action'],
					substr($path, -1, 1) === '/' ? 'dir' : 'file',
					$this->_pathsMap[$path],
					$copy_from_revision,
					isset($copy_from_path_id) ? $copy_from_path_id : null
				);

				if ( $project_path ) {
					// Create missing project & add commit to it.
					if ( !isset($this->_projectsMap[$project_path]) ) {
						$this->_projectsMap[$project_path] = $this->_repositoryFiller->addProject($project_path);
					}

					if ( !in_array($project_path, $commit_projects[$revision]) ) {
						$this->_repositoryFiller->addCommitToProject($revision, $this->_projectsMap[$project_path]);
						$commit_projects[$revision][] = $project_path;
					}

					if ( $ref_name ) {
						$project_id = $this->_projectsMap[$project_path];
						$ref_key = $project_id . ':' . $ref_name;

						// Create missing project ref and add commit to it.
						if ( !isset($this->_refsMap[$ref_key]) ) {
							$this->_refsMap[$ref_key] = $this->_repositoryFiller->addRefToProject(
								$ref_name,
								$project_id
							);
						}

						if ( !in_array($ref_key, $commit_refs[$revision]) ) {
							$this->_repositoryFiller->addCommitToRef($revision, $this->_refsMap[$ref_key]);
							$commit_refs[$revision][] = $ref_key;
						}
					}
				}
			}
		}

		$this->_commits = array();
	}

}
