<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\CommitMessage;


class GroupByBugMergeTemplate extends AbstractGroupByMergeTemplate
{

	/**
	 * Returns merge template name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'group_by_bug';
	}

	/**
	 * Builds group body.
	 *
	 * @param string  $path            Path.
	 * @param array   $revisions       Revisions.
	 * @param string  $repository_url  Repository URL.
	 * @param string  $relative_path   Relative path.
	 * @param boolean $merge_direction Merge direction.
	 *
	 * @return string
	 */
	protected function generateGroupBody($path, array $revisions, $repository_url, $relative_path, $merge_direction)
	{
		$merged_messages = array();
		$revision_log = $this->revisionLogFactory->getRevisionLog($repository_url . $path);
		$revisions_data = $revision_log->getRevisionsData('summary', $revisions);
		$revisions_grouped = $this->groupRevisionsByBugs($revision_log->getRevisionsData('bugs', $revisions));

		$unprocessed_revisions = $revisions;

		// Group revisions without bugs.
		foreach ( $revisions_grouped as $bug_revisions ) {
			$bug_title_added = false;

			foreach ( $bug_revisions as $revision ) {
				$commit_message_parts = explode(PHP_EOL, $revisions_data[$revision]['msg']);
				$commit_message_parts = array_filter($commit_message_parts); // Removes empty lines.

				$bug_title = array_shift($commit_message_parts);
				$commit_message = $commit_message_parts ? implode(PHP_EOL, $commit_message_parts) : '(no details)';

				if ( !$bug_title_added ) {
					$merged_messages[] = ' * ' . $bug_title;
					$bug_title_added = true;
				}

				$merged_messages[] = 'r' . $revision . ': ' . $commit_message;
			}

			$unprocessed_revisions = array_diff($unprocessed_revisions, $bug_revisions);
		}

		// Group revisions without bugs.
		if ( $unprocessed_revisions ) {
			$merged_messages[] = 'Revisions without Bug IDs:';

			foreach ( $unprocessed_revisions as $revision ) {
				$merged_messages[] = ' * r' . $revision . ': ' . $revisions_data[$revision]['msg'];
			}
		}

		$merged_messages = array_unique(array_map('trim', $merged_messages));

		if ( ($revisions_grouped && $unprocessed_revisions)
			|| count($revisions_grouped) > 1
			|| count($unprocessed_revisions) > 1
		) {
			$ret = '';
			$ret .= $this->generateGroupHeading($path, $relative_path, $merge_direction) . PHP_EOL;
			$ret .= implode(PHP_EOL, $merged_messages);

			return $ret;
		}


		$ret = '';
		$ret .= $this->generateGroupHeading($path, $relative_path, $merge_direction, false) . ': ';
		$ret .= implode(PHP_EOL, $merged_messages);

		return $ret;
	}

	/**
	 * Groups revisions by bugs.
	 *
	 * @param array $revisions_bugs Revisions bugs.
	 *
	 * @return array
	 */
	protected function groupRevisionsByBugs(array $revisions_bugs)
	{
		$ret = array();

		foreach ( $revisions_bugs as $revision => $revision_bugs ) {
			foreach ( $revision_bugs as $bug ) {
				if ( !isset($ret[$bug]) ) {
					$ret[$bug] = array();
				}

				$ret[$bug][] = $revision;
			}
		}

		return $ret;
	}

}
