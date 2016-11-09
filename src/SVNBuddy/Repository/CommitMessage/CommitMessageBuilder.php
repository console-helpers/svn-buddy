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


use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;

class CommitMessageBuilder
{

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	protected $workingCopyConflictTracker;

	/**
	 * Creates commit message builder instance.
	 *
	 * @param WorkingCopyConflictTracker $working_copy_conflict_tracker Working copy conflict tracker.
	 */
	public function __construct(WorkingCopyConflictTracker $working_copy_conflict_tracker)
	{
		$this->workingCopyConflictTracker = $working_copy_conflict_tracker;
	}

	/**
	 * Builds a commit message.
	 *
	 * @param string                $wc_path        Working copy path.
	 * @param AbstractMergeTemplate $merge_template Merge template.
	 * @param string|null           $changelist     Changelist.
	 *
	 * @return string
	 */
	public function build($wc_path, AbstractMergeTemplate $merge_template, $changelist = null)
	{
		$commit_message_parts = array();

		if ( strlen($changelist) ) {
			$commit_message_parts[] = trim($changelist);
		}

		$commit_message_parts[] = $merge_template->apply($wc_path);
		$commit_message_parts[] = $this->getFragmentForRecentConflicts($wc_path);

		return implode(PHP_EOL, array_filter($commit_message_parts));
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
