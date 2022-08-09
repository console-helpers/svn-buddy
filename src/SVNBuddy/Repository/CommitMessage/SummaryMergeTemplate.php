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


class SummaryMergeTemplate extends AbstractMergeTemplate
{

	/**
	 * Returns merge template name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'summary';
	}

	/**
	 * Applies merge template to a working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	public function apply($wc_path)
	{
		$merged_revisions = $this->repositoryConnector->getMergedRevisionChanges($wc_path, true);
		$unmerged_revisions = $this->repositoryConnector->getMergedRevisionChanges($wc_path, false);
		$has_merged_revisions = $this->flattenMergedRevisions($merged_revisions);
		$has_unmerged_revisions = $this->flattenMergedRevisions($unmerged_revisions);

		if ( !$has_merged_revisions && !$has_unmerged_revisions ) {
			return '';
		}

		$target = $this->getMomentInTime(
			$this->repositoryConnector->getRelativePath($wc_path),
			$this->repositoryConnector->getLastRevision($wc_path)
		);

		$ret = '';

		foreach ( $merged_revisions as $path => $revisions ) {
			// No changes on particular path.
			if ( !$revisions ) {
				continue;
			}

			$source = $this->getMomentInTime($path, max($revisions));
			$ret .= PHP_EOL . 'Merge of "' . $source . '" to "' . $target . '".' . PHP_EOL;
		}

		foreach ( $unmerged_revisions as $path => $revisions ) {
			// No changes on particular path.
			if ( !$revisions ) {
				continue;
			}

			$source = $this->getMomentInTime($path, max($revisions));
			$ret .= PHP_EOL . 'Reverse-merge of "' . $source . '" to "' . $target . '".' . PHP_EOL;
		}

		return trim($ret);
	}

	/**
	 * Returns moment in time.
	 *
	 * @param string  $path     Path.
	 * @param integer $revision Revision.
	 *
	 * @return string
	 */
	protected function getMomentInTime($path, $revision)
	{
		return ltrim($path, '/') . '@' . $revision;
	}

}
