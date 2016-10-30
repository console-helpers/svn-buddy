<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;

class CommitMessageBuilder
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	protected $revisionListParser;

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
	 * @param RevisionListParser         $revision_list_parser          Revision list parser.
	 * @param RevisionLogFactory         $revision_log_factory          Revision log factory.
	 * @param WorkingCopyConflictTracker $working_copy_conflict_tracker Working copy conflict tracker.
	 */
	public function __construct(
		Connector $repository_connector,
		RevisionListParser $revision_list_parser,
		RevisionLogFactory $revision_log_factory,
		WorkingCopyConflictTracker $working_copy_conflict_tracker
	) {
		$this->repositoryConnector = $repository_connector;
		$this->revisionListParser = $revision_list_parser;
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
		$merged_revisions = $this->getFreshMergedRevisions($wc_path);

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
	 * Returns list of just merged revisions.
	 *
	 * @param string $wc_path Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function getFreshMergedRevisions($wc_path)
	{
		$final_paths = array();
		$old_paths = $this->getMergedRevisions($wc_path, 'BASE');
		$new_paths = $this->getMergedRevisions($wc_path);

		if ( $old_paths === $new_paths ) {
			return array();
		}

		foreach ( $new_paths as $new_path => $new_merged_revisions ) {
			if ( !isset($old_paths[$new_path]) ) {
				// Merge from new path.
				$final_paths[$new_path] = $this->revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
			}
			elseif ( $new_merged_revisions != $old_paths[$new_path] ) {
				// Merge on existing path.
				$new_merged_revisions_parsed = $this->revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
				$old_merged_revisions_parsed = $this->revisionListParser->expandRanges(
					explode(',', $old_paths[$new_path])
				);
				$final_paths[$new_path] = array_values(
					array_diff($new_merged_revisions_parsed, $old_merged_revisions_parsed)
				);
			}
		}

		return $final_paths;
	}

	/**
	 * Returns list of merged revisions per path.
	 *
	 * @param string  $wc_path  Merge target: working copy path.
	 * @param integer $revision Revision.
	 *
	 * @return array
	 */
	protected function getMergedRevisions($wc_path, $revision = null)
	{
		$paths = array();

		$merge_info = $this->repositoryConnector->getProperty('svn:mergeinfo', $wc_path, $revision);
		$merge_info = array_filter(explode("\n", $merge_info));

		foreach ( $merge_info as $merge_info_line ) {
			list($path, $revisions) = explode(':', $merge_info_line, 2);
			$paths[$path] = $revisions;
		}

		return $paths;
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
