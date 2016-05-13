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


class Commit
{

	/**
	 * Commit data.
	 *
	 * @var array
	 */
	private $_commitData = array();

	/**
	 * Paths.
	 *
	 * @var array
	 */
	private $_paths = array();

	/**
	 * Bugs.
	 *
	 * @var array
	 */
	private $_bugs = array();

	/**
	 * Merged commits.
	 *
	 * @var array
	 */
	private $_mergedCommits = array();

	/**
	 * Creates commit builder.
	 *
	 * @param integer $revision Revision.
	 * @param string  $author   Author.
	 * @param integer $date     Date.
	 * @param string  $message  Message.
	 */
	public function __construct($revision, $author, $date, $message)
	{
		$this->_commitData = array(
			'revision' => $revision,
			'author' => $author,
			'date' => $date,
			'message' => $message,
		);
	}

	/**
	 * Returns commit data.
	 *
	 * @return array
	 */
	public function getCommitData()
	{
		return $this->_commitData;
	}

	/**
	 * Adds path.
	 *
	 * @param string      $action             Action.
	 * @param string      $path               Path.
	 * @param string      $ref_name           Ref Name.
	 * @param string      $project_path       Project path.
	 * @param string|null $copy_from_path     Copied from path.
	 * @param string|null $copy_from_revision Copied from revision.
	 *
	 * @return Commit
	 */
	public function addPath(
		$action,
		$path,
		$ref_name,
		$project_path,
		$copy_from_path = null,
		$copy_from_revision = null
	) {
		$this->_paths[$path] = array(
			'action' => $action,
			'ref_name' => $ref_name,
			'project_path' => $project_path,
			'copy_from_path' => $copy_from_path,
			'copy_from_revision' => $copy_from_revision,
		);

		return $this;
	}

	/**
	 * Returns paths.
	 *
	 * @return array
	 */
	public function getPaths()
	{
		return $this->_paths;
	}

	/**
	 * Adds bugs.
	 *
	 * @param array $bugs Bugs.
	 *
	 * @return self
	 */
	public function addBugs(array $bugs)
	{
		$this->_bugs = array_unique(array_merge($this->_bugs, $bugs));

		return $this;
	}

	/**
	 * Returns bugs.
	 *
	 * @return array
	 */
	public function getBugs()
	{
		return $this->_bugs;
	}

	/**
	 * Adds merged commits.
	 *
	 * @param array $merged_revisions Merged revisions.
	 *
	 * @return self
	 */
	public function addMergedCommits(array $merged_revisions)
	{
		$this->_mergedCommits = array_unique(array_merge($this->_mergedCommits, $merged_revisions));

		return $this;
	}

	/**
	 * Returns merged commits.
	 *
	 * @return array
	 */
	public function getMergedCommits()
	{
		return $this->_mergedCommits;
	}

}
