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


class SummaryRevisionLogPlugin extends AbstractRevisionLogPlugin
{
	const CACHE_FORMAT_VERSION = 1;

	/**
	 * Bugs affected by specific revision.
	 *
	 * @var array
	 */
	private $_revisionSummary = array();

	/**
	 * Revisions made by specific author.
	 *
	 * @var array
	 */
	private $_authorRevisions = array();

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'summary';
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
			$author = (string)$log_entry->author;

			if ( !isset($this->_authorRevisions[$author]) ) {
				$this->_authorRevisions[$author] = array();
			}

			$this->_authorRevisions[$author][] = $revision;

			$this->_revisionSummary[$revision] = array(
				'author' => $author,
				'date' => strtotime($log_entry->date),
				'msg' => (string)$log_entry->msg,
			);
		}
	}

	/**
	 * Find revisions by collected data.
	 *
	 * @param array $criteria Criteria.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When unsupported search field given.
	 * @throws \InvalidArgumentException When malformed criterion given (e.g. no field name).
	 */
	public function find(array $criteria)
	{
		$summary_revisions = array();

		foreach ( $criteria as $criterion ) {
			if ( strpos($criterion, ':') === false ) {
				$error_msg = 'Each criterion of "%s" plugin must be in "%s" format.';
				throw new \InvalidArgumentException(sprintf($error_msg, $this->getName(), 'field:value'));
			}

			list ($field, $value) = explode(':', $criterion, 2);

			if ( $field === 'author' ) {
				if ( !array_key_exists($value, $this->_authorRevisions) ) {
					continue;
				}

				foreach ( $this->_authorRevisions[$value] as $revision ) {
					$summary_revisions[$revision] = true;
				}
			}
			else {
				$error_msg = 'Searching by "%s" is not supported by "%s" plugin.';
				throw new \InvalidArgumentException(sprintf($error_msg, $field, $this->getName()));
			}
		}

		$summary_revisions = array_keys($summary_revisions);
		sort($summary_revisions, SORT_NUMERIC);

		return $summary_revisions;
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
		if ( !isset($this->_revisionSummary[$revision]) ) {
			$error_msg = 'Revision "%s" not found by "%s" plugin.';
			throw new \InvalidArgumentException(sprintf($error_msg, $revision, $this->getName()));
		}

		return $this->_revisionSummary[$revision];
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
			if ( isset($this->_revisionSummary[$revision]) ) {
				$results[$revision] = $this->_revisionSummary[$revision];
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
			'revision_summary' => $this->_revisionSummary,
			'author_revisions' => $this->_authorRevisions,
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
		$this->_revisionSummary = $collected_data['revision_summary'];
		$this->_authorRevisions = $collected_data['author_revisions'];
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
		if ( !$this->_revisionSummary ) {
			return null;
		}

		end($this->_revisionSummary);

		return key($this->_revisionSummary);
	}

}
