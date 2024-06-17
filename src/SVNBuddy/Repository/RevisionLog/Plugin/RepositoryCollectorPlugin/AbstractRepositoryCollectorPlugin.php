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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IOverwriteAwarePlugin;

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

		if ( $this instanceof IOverwriteAwarePlugin && $this->isOverwriteMode() ) {
			foreach ( $log->logentry as $log_entry ) {
				$revision = (int)$log_entry['revision'];

				$this->remove($revision);
				$this->doParse($revision, $log_entry);

				// When revision appeared only after overwrite parsing process.
				if ( $revision > $last_revision ) {
					$last_processed_revision = $revision;
				}
			}
		}
		else {
			foreach ( $log->logentry as $log_entry ) {
				$revision = (int)$log_entry['revision'];

				// Don't handle same revision twice.
				if ( $revision <= $last_revision ) {
					continue;
				}

				$this->doParse($revision, $log_entry);
				$last_processed_revision = $revision;
			}
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
	 * Removes changes plugin made based on a given revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return void
	 */
	abstract protected function remove($revision);

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
