<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Repository\RevisionLog;


use aik099\SVNBuddy\Repository\Parser\LogMessageParser;

class BugsRevisionLogPlugin implements IRevisionLogPlugin
{
	const CACHE_FORMAT_VERSION = 1;

	/**
	 * Bugs affected by specific revision.
	 *
	 * @var array
	 */
	private $_revisionBugs = array();

	/**
	 * Revisions affecting a specific bug.
	 *
	 * @var array
	 */
	private $_bugRevisions = array();

	/**
	 * Log message parser.
	 *
	 * @var LogMessageParser
	 */
	private $_logMessageParser;

	/**
	 * Creates bugs revision log plugin.
	 *
	 * @param LogMessageParser $log_message_parser Log message parser.
	 */
	public function __construct(LogMessageParser $log_message_parser)
	{
		$this->_logMessageParser = $log_message_parser;
	}

	/**
	 * Returns plugin name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'bugs';
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
			$this->_revisionBugs[$revision] = $this->_logMessageParser->parse((string)$log_entry->msg);

			foreach ( $this->_revisionBugs[$revision] as $bug_id ) {
				if ( !isset($this->_bugRevisions[$bug_id]) ) {
					$this->_bugRevisions[$bug_id] = array();
				}

				$this->_bugRevisions[$bug_id][] = $revision;
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
		$bug_revisions = array();

		foreach ( $criteria as $bug_id ) {
			if ( !array_key_exists($bug_id, $this->_bugRevisions) ) {
				continue;
			}

			foreach ( $this->_bugRevisions[$bug_id] as $revision ) {
				$bug_revisions[$revision] = true;
			}
		}

		$bug_revisions = array_keys($bug_revisions);
		sort($bug_revisions, SORT_NUMERIC);

		return $bug_revisions;
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
		if ( !isset($this->_revisionBugs[$revision]) ) {
			$error_msg = 'Revision "%s" not found by "%s" plugin.';
			throw new \InvalidArgumentException(sprintf($error_msg, $revision, $this->getName()));
		}

		return $this->_revisionBugs[$revision];
	}

	/**
	 * Returns data, collected by plugin.
	 *
	 * @return array
	 */
	public function getCollectedData()
	{
		return array(
			'revision_bugs' => $this->_revisionBugs,
			'bug_revisions' => $this->_bugRevisions,
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
		$this->_revisionBugs = $collected_data['revision_bugs'];
		$this->_bugRevisions = $collected_data['bug_revisions'];
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
		if ( !$this->_revisionBugs ) {
			return null;
		}

		end($this->_revisionBugs);

		return key($this->_revisionBugs);
	}

}
