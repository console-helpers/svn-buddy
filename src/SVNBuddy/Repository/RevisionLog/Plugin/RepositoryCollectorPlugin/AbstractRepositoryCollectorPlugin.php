<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\RepositoryCollectorPlugin;


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\AbstractPlugin;

abstract class AbstractRepositoryCollectorPlugin extends AbstractPlugin implements IRepositoryCollectorPlugin
{

	/**
	 * Parse log entries.
	 *
	 * @param \SimpleXMLElement $log Log.
	 *
	 * @return void
	 */
	public function parse(\SimpleXMLElement $log)
	{
		$this->database->beginTransaction();

		$last_processed_revision = null;
		$last_revision = $this->getLastRevision();

		foreach ( $log->logentry as $log_entry ) {
			$revision = (int)$log_entry['revision'];

			// Don't handle same revision twice.
			if ( $revision <= $last_revision ) {
				continue;
			}

			$this->doParse($revision, $log_entry);
			$last_processed_revision = $revision;
		}

		if ( isset($last_processed_revision) ) {
			$this->setLastRevision($last_processed_revision);
		}

		$this->database->commit();

		$this->freeMemoryAutomatically();
	}

	/**
	 * Does actual parsing.
	 *
	 * @param integer           $revision  Revision.
	 * @param \SimpleXMLElement $log_entry Log Entry.
	 *
	 * @return void
	 */
	abstract protected function doParse($revision, \SimpleXMLElement $log_entry);

	/**
	 * Returns revision query flags.
	 *
	 * @return array
	 */
	public function getRevisionQueryFlags()
	{
		return array();
	}

}
