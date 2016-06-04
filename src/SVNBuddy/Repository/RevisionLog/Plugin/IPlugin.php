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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;

interface IPlugin
{

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Method is called, when database is ready for usable by plugin.
	 *
	 * @return void
	 */
	public function whenDatabaseReady();

	/**
	 * Defines parsing statistic types.
	 *
	 * @return array
	 */
	public function defineStatisticTypes();

	/**
	 * Returns parsing statistics.
	 *
	 * @return array
	 */
	public function getStatistics();

	/**
	 * Find revisions by collected data.
	 *
	 * @param array  $criteria     Criteria.
	 * @param string $project_path Project path.
	 *
	 * @return array
	 */
	public function find(array $criteria, $project_path);

	/**
	 * Returns information about revisions.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return array
	 */
	public function getRevisionsData(array $revisions);

	/**
	 * Returns last revision processed by plugin.
	 *
	 * @return integer
	 */
	public function getLastRevision();

	/**
	 * Sets reference to revision log.
	 *
	 * @param RevisionLog $revision_log Revision log.
	 *
	 * @return void
	 */
	public function setRevisionLog(RevisionLog $revision_log);

}
