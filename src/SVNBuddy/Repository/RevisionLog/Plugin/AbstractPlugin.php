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


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RepositoryFiller;

abstract class AbstractPlugin implements IPlugin
{

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	protected $database;

	/**
	 * Repository filler.
	 *
	 * @var RepositoryFiller
	 */
	protected $repositoryFiller;

	/**
	 * Parsing statistics.
	 *
	 * @var array
	 */
	private $_statistics = array();

	/**
	 * Creates plugin instance.
	 *
	 * @param ExtendedPdoInterface $database          Database.
	 * @param RepositoryFiller     $repository_filler Repository filler.
	 */
	public function __construct(ExtendedPdoInterface $database, RepositoryFiller $repository_filler)
	{
		$this->database = $database;
		$this->repositoryFiller = $repository_filler;

		$this->_resetParsingStatistics();
	}

	/**
	 * Resets parsing statistics.
	 *
	 * @return void
	 */
	private function _resetParsingStatistics()
	{
		$this->_statistics = array();

		foreach ( $this->defineStatisticTypes() as $parsing_statistic_type ) {
			$this->_statistics[$parsing_statistic_type] = 0;
		}
	}

	/**
	 * Hook, that is called before "RevisionLog::refresh" method call.
	 *
	 * @return void
	 */
	public function whenDatabaseReady()
	{

	}

	/**
	 * Records parsing statistics.
	 *
	 * @param string  $type   Type.
	 * @param integer $to_add Number to add.
	 *
	 * @return void
	 */
	protected function recordStatistic($type, $to_add = 1)
	{
		$this->_statistics[$type] += $to_add;
	}

	/**
	 * Returns parsing statistics.
	 *
	 * @return array
	 */
	public function getStatistics()
	{
		return $this->_statistics;
	}

	/**
	 * Adds results for missing revisions.
	 *
	 * @param array $revisions Revisions.
	 * @param array $results   Results.
	 *
	 * @return array
	 */
	protected function addMissingResults(array $revisions, array $results)
	{
		foreach ( $this->_getMissingRevisions($revisions, $results) as $missing_revision ) {
			$results[$missing_revision] = array();
		}

		return $results;
	}

	/**
	 * Adds results for missing revisions.
	 *
	 * @param array $revisions Revisions.
	 * @param array $results   Results.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When some revisions are missing in results.
	 */
	protected function assertNoMissingRevisions(array $revisions, array $results)
	{
		$missing_revisions = $this->_getMissingRevisions($revisions, $results);

		if ( !$missing_revisions ) {
			return;
		}

		throw new \InvalidArgumentException(sprintf(
			'Revision(-s) "%s" not found by "%s" plugin.',
			implode('", "', $missing_revisions),
			$this->getName()
		));
	}

	/**
	 * Returns revisions, that are missing in results.
	 *
	 * @param array $revisions Revisions.
	 * @param array $results   Results.
	 *
	 * @return array
	 */
	private function _getMissingRevisions(array $revisions, array $results)
	{
		return array_diff($revisions, array_keys($results));
	}

	/**
	 * Returns last revision processed by plugin.
	 *
	 * @return integer
	 */
	public function getLastRevision()
	{
		$sql = 'SELECT LastRevision
				FROM PluginData
				WHERE Name = :name';
		$last_revision = $this->database->fetchValue($sql, array('name' => $this->getName()));

		return $last_revision !== false ? $last_revision : 0;
	}

	/**
	 * Sets last revision processed by plugin.
	 *
	 * @param integer $last_revision Last revision.
	 *
	 * @return void
	 */
	protected function setLastRevision($last_revision)
	{
		$sql = 'REPLACE INTO PluginData (Name, LastRevision)
				VALUES (:name, :last_revision)';
		$this->database->perform($sql, array('name' => $this->getName(), 'last_revision' => $last_revision));
	}

	/**
	 * Finds project by path.
	 *
	 * @param string $path Path.
	 *
	 * @return integer
	 * @throws \InvalidArgumentException When project can't be found.
	 */
	protected function getProject($path)
	{
		$sql = 'SELECT Id
				FROM Projects
				WHERE Path = :path';
		$project_id = $this->database->fetchValue($sql, array('path' => $path));

		if ( $project_id === false ) {
			throw new \InvalidArgumentException('The project with "' . $path . '" path not found.');
		}

		return $project_id;
	}

	/**
	 * Automatically free memory, when >200MB is used.
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	protected function freeMemoryAutomatically()
	{
		$memory_usage = memory_get_usage();

		if ( $memory_usage > 200 * 1024 * 1024 ) {
			$this->freeMemoryManually();
		}
	}

	/**
	 * Frees consumed memory manually.
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	protected function freeMemoryManually()
	{
		$profiler = $this->database->getProfiler();

		if ( is_object($profiler) && $profiler->isActive() ) {
			$profiler->resetProfiles();
		}
	}

}
