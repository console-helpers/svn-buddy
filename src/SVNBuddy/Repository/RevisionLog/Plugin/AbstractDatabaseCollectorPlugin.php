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


use Symfony\Component\Console\Helper\ProgressBar;

abstract class AbstractDatabaseCollectorPlugin extends AbstractPlugin implements IDatabaseCollectorPlugin
{

	/**
	 * Progress bar.
	 *
	 * @var ProgressBar
	 */
	private $_progressBar;

	/**
	 * Processes data.
	 *
	 * @param integer     $from_revision From revision.
	 * @param integer     $to_revision   To revision.
	 * @param ProgressBar $progress_bar  Progress bar.
	 *
	 * @return void
	 */
	public function process($from_revision, $to_revision, ProgressBar $progress_bar = null)
	{
		$this->_progressBar = $progress_bar;

		$this->database->beginTransaction();
		$this->doProcess($from_revision, $to_revision);
		$this->database->commit();

		$this->freeMemoryAutomatically();
	}

	/**
	 * Processes data.
	 *
	 * @param integer $from_revision From revision.
	 * @param integer $to_revision   To revision.
	 *
	 * @return void
	 */
	abstract public function doProcess($from_revision, $to_revision);

	/**
	 * Advanced progress bar.
	 *
	 * @return void
	 */
	protected function advanceProgressBar()
	{
		if ( isset($this->_progressBar) ) {
			$this->_progressBar->advance();
		}
	}

	/**
	 * Returns projects.
	 *
	 * @param string $where_clause Where clause.
	 * @param array  $values       Values.
	 *
	 * @return array
	 */
	protected function getProjects($where_clause = '', array $values = array())
	{
		$sql = 'SELECT *
				FROM Projects';

		if ( $where_clause ) {
			$sql .= ' WHERE ' . $where_clause;
		}

		$projects = $this->database->fetchAll($sql, $values);

		if ( !$projects ) {
			return array();
		}

		$path_hashes = array_map(
			array($this->repositoryFiller, 'getPathChecksum'),
			$this->getField('Path', $projects)
		);

		$sql = 'SELECT Path, Id AS PathId, RevisionAdded, RevisionDeleted, RevisionLastSeen
				FROM Paths
				WHERE PathHash IN (:path_hashes)';
		$paths = $this->database->fetchAssoc($sql, array('path_hashes' => $path_hashes));

		foreach ( $projects as $index => $project_data ) {
			$project_path = $project_data['Path'];
			$projects[$index] = array_merge($projects[$index], $paths[$project_path]);
		}

		return $projects;
	}

	/**
	 * Returns given column value from each array entry.
	 *
	 * @param string $field   Field.
	 * @param array  $records Records.
	 *
	 * @return array
	 */
	protected function getField($field, array $records)
	{
		$ret = array();

		foreach ( $records as $row ) {
			$ret[] = $row[$field];
		}

		return $ret;
	}

}
