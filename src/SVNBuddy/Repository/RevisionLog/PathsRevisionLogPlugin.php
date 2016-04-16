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


class PathsRevisionLogPlugin extends AbstractRevisionLogPlugin
{
	const CACHE_FORMAT_VERSION = 1;

	/**
	 * Paths affected by specific revision.
	 *
	 * @var array
	 */
	private $_revisionPaths = array();

	/**
	 * Revisions affecting a specific path.
	 *
	 * @var array
	 */
	private $_pathRevisions = array();

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'paths';
	}

	/**
	 * Parse log entries.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	public function parse(\SimpleXMLElement $log)
	{
		foreach ( $log->logentry as $log_entry ) {
			$revision = (int)$log_entry['revision'];
			$this->_revisionPaths[$revision] = array();

			foreach ( $log_entry->paths->path as $path_node ) {
				/** @var \SimpleXMLElement $path_node */
				$path = (string)$path_node;

				if ( !isset($this->_pathRevisions[$path]) ) {
					$this->_pathRevisions[$path] = array();
				}

				$this->_pathRevisions[$path][] = $revision;

				$path_data = array('path' => $path);

				foreach ( $path_node->attributes() as $attribute_name => $attribute_value ) {
					$path_data[$attribute_name] = (string)$attribute_value;
				}

				$this->_revisionPaths[$revision][] = $path_data;
			}
		}
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array $criteria Criteria.
	 *
	 * @return array
	 */
	public function find(array $criteria)
	{
		if ( reset($criteria) === '' ) {
			// Include revisions from all paths.
			$path_revisions = $this->_revisionPaths;
		}
		else {
			// Include revisions from given sub-path only.
			$path_revisions = array();

			foreach ( $criteria as $path ) {
				$path_length = strlen($path);

				foreach ( $this->_pathRevisions as $test_path => $revisions ) {
					// FIXME: Fast, but does sub-match in inside a folder and "tags/stable" matches also "tags/stable2".
					if ( substr($test_path, 0, $path_length) == $path ) {
						foreach ( $revisions as $revision ) {
							$path_revisions[$revision] = true;
						}
					}
				}
			}
		}

		$path_revisions = array_keys($path_revisions);
		sort($path_revisions, SORT_NUMERIC);

		return $path_revisions;
	}

	/**
	 * Returns information about revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When revision is not found.
	 */
	public function getRevisionData($revision)
	{
		if ( !isset($this->_revisionPaths[$revision]) ) {
			$error_msg = 'Revision "%s" not found by "%s" plugin.';
			throw new \InvalidArgumentException(sprintf($error_msg, $revision, $this->getName()));
		}

		return $this->_revisionPaths[$revision];
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

		foreach ( $revisions as $revision ) {
			if ( isset($this->_revisionPaths[$revision]) ) {
				$results[$revision] = $this->_revisionPaths[$revision];
			}
		}

		$this->assertNoMissingRevisions($revisions, $results);

		return $results;
	}

	/**
	 * Returns data, collected by plugin.
	 *
	 * @return array
	 */
	public function getCollectedData()
	{
		return array(
			'revision_paths' => $this->_revisionPaths,
			'path_revisions' => $this->_pathRevisions,
		);
	}

	/**
	 * Initializes plugin using previously collected data.
	 *
	 * @param array $collected_data Collected data.
	 *
	 * @return void
	 */
	public function setCollectedData(array $collected_data)
	{
		$this->_revisionPaths = $collected_data['revision_paths'];
		$this->_pathRevisions = $collected_data['path_revisions'];
	}

	/**
	 * Returns cache invalidator for this plugin data.
	 *
	 * @return string
	 */
	public function getCacheInvalidator()
	{
		return self::CACHE_FORMAT_VERSION;
	}

	/**
	 * Returns last known revision number.
	 *
	 * @return integer
	 */
	public function getLastRevision()
	{
		if ( !$this->_revisionPaths ) {
			return null;
		}

		end($this->_revisionPaths);

		return key($this->_revisionPaths);
	}

}
