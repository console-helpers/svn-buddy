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


class MergesRevisionLogPlugin implements IRevisionLogPlugin
{
	const CACHE_FORMAT_VERSION = 1;

	/**
	 * List of merged revisions (key - merged revision, value - array of revisions in which it was merged).
	 *
	 * @var array
	 */
	private $_mergedRevisions = array();

	/**
	 * List of merge revisions (key - merge revision, value - array of revisions merged by this revision).
	 *
	 * @var array
	 */
	private $_mergeRevisions = array();

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'merges';
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
			$revision_merges = array();
			$revision = (int)$log_entry['revision'];

			foreach ( $log_entry->logentry as $merged_log_entry ) {
				$merged_revision = (int)$merged_log_entry['revision'];

				$revision_merges[] = $merged_revision;

				if ( !isset($this->_mergedRevisions[$merged_revision]) ) {
					$this->_mergedRevisions[$merged_revision] = array();
				}

				$this->_mergedRevisions[$merged_revision][] = $revision;
			}

			if ( $revision_merges ) {
				$this->_mergeRevisions[$revision] = $revision_merges;
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
		$merged_revisions = array();

		foreach ( $criteria as $merge_revision ) {
			if ( !array_key_exists($merge_revision, $this->_mergeRevisions) ) {
				continue;
			}

			foreach ( $this->_mergeRevisions[$merge_revision] as $merged_revision ) {
				$merged_revisions[$merged_revision] = true;
			}
		}

		$merged_revisions = array_keys($merged_revisions);
		sort($merged_revisions, SORT_NUMERIC);

		return $merged_revisions;
	}

	/**
	 * Returns information about revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return array
	 */
	public function getRevisionData($revision)
	{
		// When revision wasn't yet merged, the merge revisions list is empty.
		return isset($this->_mergedRevisions[$revision]) ? $this->_mergedRevisions[$revision] : array();
	}

	/**
	 * Returns data, collected by plugin.
	 *
	 * @return array
	 */
	public function getCollectedData()
	{
		return array(
			'merge_revisions' => $this->_mergeRevisions,
			'merged_revisions' => $this->_mergedRevisions,
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
		$this->_mergeRevisions = $collected_data['merge_revisions'];
		$this->_mergedRevisions = $collected_data['merged_revisions'];
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
		if ( !$this->_mergeRevisions ) {
			return null;
		}

		end($this->_mergeRevisions);

		return key($this->_mergeRevisions);
	}

}
