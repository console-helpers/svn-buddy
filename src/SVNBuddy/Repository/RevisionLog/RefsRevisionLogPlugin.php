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


use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class RefsRevisionLogPlugin extends AbstractRevisionLogPlugin
{
	const CACHE_FORMAT_VERSION = 1;

	/**
	 * Refs affected by specific revision.
	 *
	 * @var array
	 */
	private $_revisionRefs = array();

	/**
	 * Revisions affecting a specific ref.
	 *
	 * @var array
	 */
	private $_refRevisions = array();

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Create refs revision log plugin.
	 *
	 * @param Connector $repository_connector Repository connector.
	 */
	public function __construct(Connector $repository_connector)
	{
		$this->_repositoryConnector = $repository_connector;
	}

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
	 * Parse log entries.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	public function parse(\SimpleXMLElement $log)
	{
		foreach ( $log->logentry as $log_entry ) {
			$revision_refs = array();
			$revision = (int)$log_entry['revision'];

			foreach ( $log_entry->paths->path as $path_node ) {
				/** @var \SimpleXMLElement $path_node */
				$path = (string)$path_node;

				$ref = $this->_repositoryConnector->getRefByPath($path);

				if ( $ref !== false ) {
					$revision_refs[$ref] = true;
				}
			}

			// Path in commit is in unknown format.
			if ( !$revision_refs ) {
				continue;
			}

			$this->_revisionRefs[$revision] = array();

			foreach ( array_keys($revision_refs) as $ref ) {
				if ( !isset($this->_refRevisions[$ref]) ) {
					$this->_refRevisions[$ref] = array();
				}

				$this->_refRevisions[$ref][] = $revision;
				$this->_revisionRefs[$revision][] = $ref;
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
		if ( reset($criteria) === 'all_refs' ) {
			return array_keys($this->_refRevisions);
		}

		$ref_revisions = array();

		foreach ( $criteria as $ref ) {
			if ( !isset($this->_refRevisions[$ref]) ) {
				continue;
			}

			foreach ( $this->_refRevisions[$ref] as $revision ) {
				$ref_revisions[$revision] = true;
			}
		}

		$ref_revisions = array_keys($ref_revisions);
		sort($ref_revisions, SORT_NUMERIC);

		return $ref_revisions;
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
		if ( !isset($this->_revisionRefs[$revision]) ) {
			$error_msg = 'Revision "%s" not found by "%s" plugin.';
			throw new \InvalidArgumentException(sprintf($error_msg, $revision, $this->getName()));
		}

		return $this->_revisionRefs[$revision];
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
			if ( isset($this->_revisionRefs[$revision]) ) {
				$results[$revision] = $this->_revisionRefs[$revision];
			}
		}

		return $this->addMissingResults($revisions, $results);
	}

	/**
	 * Returns data, collected by plugin.
	 *
	 * @return array
	 */
	public function getCollectedData()
	{
		return array(
			'revision_refs' => $this->_revisionRefs,
			'ref_revisions' => $this->_refRevisions,
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
		$this->_revisionRefs = $collected_data['revision_refs'];
		$this->_refRevisions = $collected_data['ref_revisions'];
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
		if ( !$this->_revisionRefs ) {
			return null;
		}

		end($this->_revisionRefs);

		return key($this->_revisionRefs);
	}

}
