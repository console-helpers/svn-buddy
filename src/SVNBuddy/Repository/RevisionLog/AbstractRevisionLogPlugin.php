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


abstract class AbstractRevisionLogPlugin implements IRevisionLogPlugin
{

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

}
