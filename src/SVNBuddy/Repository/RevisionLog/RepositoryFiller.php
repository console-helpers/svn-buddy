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


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;

class RepositoryFiller
{

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	protected $database;

	/**
	 * Database cache.
	 *
	 * @var DatabaseCache
	 */
	protected $databaseCache;

	/**
	 * RepositoryFiller constructor.
	 *
	 * @param ExtendedPdoInterface $database       Database.
	 * @param DatabaseCache        $database_cache Database cache.
	 */
	public function __construct(ExtendedPdoInterface $database, DatabaseCache $database_cache)
	{
		$this->database = $database;
		$this->databaseCache = $database_cache;

		$this->databaseCache->cacheTable('Paths');
	}

	/**
	 * Creates a project.
	 *
	 * @param string      $path       Path.
	 * @param integer     $is_deleted Is Deleted.
	 * @param string|null $bug_regexp Bug regexp.
	 *
	 * @return integer
	 */
	public function addProject($path, $is_deleted = 0, $bug_regexp = null)
	{
		$sql = 'INSERT INTO Projects (Path, IsDeleted, BugRegExp)
				VALUES (:path, :is_deleted, :bug_regexp)';
		$this->database->perform($sql, array(
			'path' => $path,
			'is_deleted' => $is_deleted,
			'bug_regexp' => $bug_regexp,
		));

		$project_id = $this->database->lastInsertId();

		// There are no "0" revision in repository, but we need to bind project path to some revision.
		if ( $path === '/' ) {
			$this->addPath($path, '', $path, 0);
		}

		return $project_id;
	}

	/**
	 * Changes project status.
	 *
	 * @param integer $project_id Project ID.
	 * @param integer $is_deleted Is deleted flag.
	 *
	 * @return void
	 */
	public function setProjectStatus($project_id, $is_deleted)
	{
		$sql = 'UPDATE Projects
				SET IsDeleted = :is_deleted
				WHERE Id = :id';
		$this->database->perform($sql, array(
			'is_deleted' => (int)$is_deleted,
			'id' => $project_id,
		));
	}

	/**
	 * Changes project bug regexp.
	 *
	 * @param integer     $project_id Project ID.
	 * @param string|null $bug_regexp Bug regexp.
	 *
	 * @return void
	 */
	public function setProjectBugRegexp($project_id, $bug_regexp)
	{
		$sql = 'UPDATE Projects
				SET BugRegExp = :bug_regexp
				WHERE Id = :id';
		$this->database->perform($sql, array(
			'bug_regexp' => $bug_regexp,
			'id' => $project_id,
		));
	}

	/**
	 * Adds commit.
	 *
	 * @param integer $revision Revision.
	 * @param string  $author   Author.
	 * @param integer $date     Date.
	 * @param string  $message  Message.
	 *
	 * @return void
	 */
	public function addCommit($revision, $author, $date, $message)
	{
		$sql = 'INSERT INTO Commits (Revision, Author, Date, Message)
				VALUES (:revision, :author, :date, :message)';
		$this->database->perform($sql, array(
			'revision' => $revision,
			'author' => $author,
			'date' => $date,
			'message' => $message,
		));
	}

	/**
	 * Adds commit to project.
	 *
	 * @param integer $revision   Revision.
	 * @param integer $project_id Project ID.
	 *
	 * @return void
	 */
	public function addCommitToProject($revision, $project_id)
	{
		$sql = 'INSERT INTO CommitProjects (ProjectId, Revision)
				VALUES (:project_id, :revision)';
		$this->database->perform($sql, array('project_id' => $project_id, 'revision' => $revision));
	}

	/**
	 * Adds path.
	 *
	 * @param string  $path         Path.
	 * @param string  $ref          Ref.
	 * @param string  $project_path Project path.
	 * @param integer $revision     Revision.
	 *
	 * @return integer
	 */
	public function addPath($path, $ref, $project_path, $revision)
	{
		$sql = 'INSERT INTO Paths (
					Path, PathNestingLevel, PathHash, RefName, ProjectPath, RevisionAdded, RevisionLastSeen
				)
				VALUES (:path, :path_nesting_level, :path_hash, :ref, :project_path, :revision, :revision)';
		$this->database->perform($sql, array(
			'path' => $path,
			'path_nesting_level' => substr_count($path, '/') - 1,
			'path_hash' => $this->getPathChecksum($path),
			'ref' => $ref,
			'project_path' => $project_path,
			'revision' => $revision,
		));
		$path_id = $this->database->lastInsertId();

		$this->propagateRevisionLastSeen($path, $revision);

		return $path_id;
	}

	/**
	 * Adds path to commit.
	 *
	 * @param integer      $revision      Revision.
	 * @param string       $action        Action.
	 * @param string       $kind          Kind.
	 * @param integer      $path_id       Path ID.
	 * @param integer|null $copy_revision Copy revision.
	 * @param integer|null $copy_path_id  Copy path ID.
	 *
	 * @return void
	 */
	public function addPathToCommit($revision, $action, $kind, $path_id, $copy_revision = null, $copy_path_id = null)
	{
		$sql = 'INSERT INTO CommitPaths (Revision, Action, Kind, PathId, CopyRevision, CopyPathId)
				VALUES (:revision, :action, :kind, :path_id, :copy_revision, :copy_path_id)';
		$this->database->perform($sql, array(
			'revision' => $revision,
			'action' => $action,
			'kind' => $kind,
			'path_id' => $path_id,
			'copy_revision' => $copy_revision,
			'copy_path_id' => $copy_path_id,
		));
	}

	/**
	 * Touches given path.
	 *
	 * @param string  $path        Path.
	 * @param integer $revision    Revision.
	 * @param array   $fields_hash Fields hash.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When "$fields_hash" is empty.
	 */
	public function touchPath($path, $revision, array $fields_hash)
	{
		if ( !$fields_hash ) {
			throw new \InvalidArgumentException('The "$fields_hash" variable can\'t be empty.');
		}

		$path_hash = $this->getPathChecksum($path);
		$to_update = $this->propagateRevisionLastSeen($path, $revision);
		$to_update[$path_hash] = $fields_hash;

		$bind_params = array_values($fields_hash);
		$bind_params[] = $path_hash;

		$sql = 'UPDATE Paths
				SET ' . implode(' = ?, ', array_keys($fields_hash)) . ' = ?
				WHERE PathHash = ?';
		$this->database->perform($sql, $bind_params);

		return $to_update;
	}

	/**
	 * Propagates revision last seen.
	 *
	 * @param string $path     Path.
	 * @param string $revision Revision.
	 *
	 * @return array
	 */
	protected function propagateRevisionLastSeen($path, $revision)
	{
		$to_update = array();
		$update_path = $path;

		$select_sql = 'SELECT RevisionLastSeen FROM Paths WHERE PathHash = :path_hash';
		$update_sql = 'UPDATE Paths SET RevisionLastSeen = :revision WHERE PathHash = :path_hash';

		while ( ($update_path = dirname($update_path) . '/') !== '//' ) {
			$update_path_hash = $this->getPathChecksum($update_path);

			$fields_hash = $this->databaseCache->getFromCache(
				'Paths',
				$update_path_hash . '/' . __METHOD__,
				$select_sql,
				array('path_hash' => $update_path_hash)
			);

			// Missing parent path. Can happen for example, when repository was created via "cvs2svn".
			if ( $fields_hash === false ) {
				/** @var StatementProfiler $profiler */
				$profiler = $this->database->getProfiler();
				$profiler->removeProfile($select_sql, array('path_hash' => $update_path_hash));
				break;
			}

			// TODO: Collect these paths and issue single update after cycle finishes.
			if ( (int)$fields_hash['RevisionLastSeen'] < $revision ) {
				$this->database->perform(
					$update_sql,
					array('revision' => $revision, 'path_hash' => $update_path_hash)
				);

				$fields_hash = array('RevisionLastSeen' => $revision);
				$this->databaseCache->setIntoCache('Paths', $update_path_hash . '/' . __METHOD__, $fields_hash);
				$to_update[$update_path_hash] = $fields_hash;
			}
		};

		return $to_update;
	}

	/**
	 * Returns fields, that needs to be changed for given path.
	 *
	 * @param string  $action    Action.
	 * @param integer $revision  Revision.
	 * @param array   $path_data Path data.
	 *
	 * @return array
	 */
	public function getPathTouchFields($action, $revision, array $path_data)
	{
		$fields_hash = array();

		if ( $action === 'D' ) {
			$fields_hash['RevisionDeleted'] = $revision;
		}
		else {
			if ( $path_data['RevisionDeleted'] > 0 ) {
				$fields_hash['RevisionDeleted'] = null;
			}

			if ( $action === 'A' && $path_data['RevisionAdded'] > $revision ) {
				$fields_hash['RevisionAdded'] = $revision;
			}

			if ( $path_data['RevisionLastSeen'] < $revision ) {
				$fields_hash['RevisionLastSeen'] = $revision;
			}
		}

		return $fields_hash;
	}

	/**
	 * Sets project path for given paths.
	 *
	 * @param array  $path_ids     Path IDs.
	 * @param string $project_path Project path.
	 *
	 * @return void
	 */
	public function movePathsIntoProject(array $path_ids, $project_path)
	{
		$sql = 'UPDATE Paths
				SET ProjectPath = :path
				WHERE Id IN (:path_ids)';
		$this->database->perform($sql, array(
			'path' => $project_path,
			'path_ids' => $path_ids,
		));
	}

	/**
	 * Adds commit with bugs.
	 *
	 * @param array   $bugs     Bugs.
	 * @param integer $revision Revision.
	 *
	 * @return void
	 */
	public function addBugsToCommit(array $bugs, $revision)
	{
		foreach ( $bugs as $bug ) {
			$sql = 'INSERT INTO CommitBugs (Revision, Bug)
					VALUES (:revision, :bug)';
			$this->database->perform($sql, array(
				'revision' => $revision,
				'bug' => $bug,
			));
		}
	}

	/**
	 * Adds merge commit.
	 *
	 * @param integer $revision         Revision.
	 * @param array   $merged_revisions Merged revisions.
	 *
	 * @return void
	 */
	public function addMergeCommit($revision, array $merged_revisions)
	{
		foreach ( $merged_revisions as $merged_revision ) {
			$sql = 'INSERT INTO Merges (MergeRevision, MergedRevision)
					VALUES (:merge_revision, :merged_revision)';
			$this->database->perform($sql, array(
				'merge_revision' => $revision,
				'merged_revision' => $merged_revision,
			));
		}
	}

	/**
	 * Adds ref to project.
	 *
	 * @param string  $ref        Ref.
	 * @param integer $project_id Project ID.
	 *
	 * @return integer
	 */
	public function addRefToProject($ref, $project_id)
	{
		$sql = 'INSERT INTO ProjectRefs (ProjectId, Name)
				VALUES (:project_id, :name)';
		$this->database->perform($sql, array('project_id' => $project_id, 'name' => $ref));

		return $this->database->lastInsertId();
	}

	/**
	 * Adds ref to commit and commit to project.
	 *
	 * @param integer $revision Revision.
	 * @param integer $ref_id   Ref ID.
	 *
	 * @return void
	 */
	public function addCommitToRef($revision, $ref_id)
	{
		$sql = 'INSERT INTO CommitRefs (Revision, RefId)
				VALUES (:revision, :ref_id)';
		$this->database->perform($sql, array('revision' => $revision, 'ref_id' => $ref_id));
	}

	/**
	 * Returns unsigned checksum of the path.
	 *
	 * @param string $path Path.
	 *
	 * @return integer
	 */
	public function getPathChecksum($path)
	{
		return sprintf('%u', crc32($path));
	}

}
