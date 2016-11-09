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


use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;

class CommitMessageBuilder
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Revision log factory.
	 *
	 * @var RevisionLogFactory
	 */
	protected $revisionLogFactory;

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	protected $workingCopyConflictTracker;

	/**
	 * Creates commit message builder instance.
	 *
	 * @param Connector                  $repository_connector          Repository connector.
	 * @param RevisionLogFactory         $revision_log_factory          Revision log factory.
	 * @param WorkingCopyConflictTracker $working_copy_conflict_tracker Working copy conflict tracker.
	 */
	public function __construct(
		Connector $repository_connector,
		RevisionLogFactory $revision_log_factory,
		WorkingCopyConflictTracker $working_copy_conflict_tracker
	) {
		$this->repositoryConnector = $repository_connector;
		$this->revisionLogFactory = $revision_log_factory;
		$this->workingCopyConflictTracker = $working_copy_conflict_tracker;
	}

	/**
	 * Builds a commit message.
	 *
	 * @param string      $wc_path    Working copy path.
	 * @param string|null $changelist Changelist.
	 *
	 * @return string
	 */
	public function build($wc_path, $changelist = null)
	{
		/*
		 * 3. if it's In-Portal project, then:
		 * - create commit message that:
		 * -- Merge of "{from_path}@{from_rev}" to "{to_path}@{to_rev}".
		 * -- Merge of "in-portal/branches/5.2.x@16189" to "in-portal/branches/5.3.x@16188".
		 * - {from_path} to be determined from list of merged revisions
		 * - {from_rev} - last changed of {from_path} by looking in repo
		 * - {to_path} to be determined from working copy
		 * - {to_rev} - last changed of {to_path} by looking in repo
		 * 4. open interactive editor with auto-generated message
		 */

		$commit_message_parts = array();

		if ( strlen($changelist) ) {
			$commit_message_parts[] = trim($changelist);
		}

		$commit_message_parts[] = $this->getFragmentForMergedRevisions($wc_path);
		$commit_message_parts[] = $this->getFragmentForRecentConflicts($wc_path);

		return implode(PHP_EOL, array_filter($commit_message_parts));
	}

	/**
	 * Returns commit message fragment for merged revisions.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	protected function getFragmentForMergedRevisions($wc_path)
	{
		$merged_revisions = $this->repositoryConnector->getFreshMergedRevisions($wc_path);

		if ( !$merged_revisions ) {
			return '';
		}

		$ret = '';
		$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
		$repository_url = $this->repositoryConnector->getRootUrl($wc_url);

		foreach ( $merged_revisions as $path => $revisions ) {
			$merged_messages = array();
			$revision_log = $this->revisionLogFactory->getRevisionLog($repository_url . $path);
			$ret .= PHP_EOL . $this->getCommitMessageHeading($wc_url, $path) . PHP_EOL;

			$revisions_data = $revision_log->getRevisionsData('summary', $revisions);

			foreach ( $revisions as $revision ) {
				$merged_messages[] = ' * r' . $revision . ': ' . $revisions_data[$revision]['msg'];
			}

			$merged_messages = array_unique(array_map('trim', $merged_messages));
			$ret .= implode(PHP_EOL, $merged_messages) . PHP_EOL;
		}

		return trim($ret);
	}

	/**
	 * Builds commit message heading.
	 *
	 * @param string $wc_url Working copy url.
	 * @param string $path   Source path for merge operation.
	 *
	 * @return string
	 */
	protected function getCommitMessageHeading($wc_url, $path)
	{
		return 'Merging from ' . ucfirst(basename($path)) . ' to ' . ucfirst(basename($wc_url));
	}

	/**
	 * Returns commit message fragment for recent conflicts.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	protected function getFragmentForRecentConflicts($wc_path)
	{
		$recorded_conflicts = $this->workingCopyConflictTracker->getRecordedConflicts($wc_path);

		if ( !$recorded_conflicts ) {
			return '';
		}

		// Ensure empty line before.
		$ret = PHP_EOL . 'Conflicts:';

		foreach ( $recorded_conflicts as $conflict_path ) {
			$ret .= PHP_EOL . ' * ' . $conflict_path;
		}

		return $ret;
	}

}
