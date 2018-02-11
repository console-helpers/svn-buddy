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


class GroupByRevisionMergeTemplate extends AbstractMergeTemplate
{

	/**
	 * Returns merge template name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'group_by_revision';
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
		$from_path = basename($path);
		$to_path = basename($wc_url);

		if ( $from_path === $to_path ) {
			$from_project_parts = explode('/', $this->repositoryConnector->getProjectUrl($path));
			$from_path .= ' (' . end($from_project_parts) . ')';
		}

		return 'Merging from ' . ucfirst($from_path) . ' to ' . ucfirst($to_path);
	}

}
