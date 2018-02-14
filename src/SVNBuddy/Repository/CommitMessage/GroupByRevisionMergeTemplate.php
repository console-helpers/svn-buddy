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
		$relative_path = $this->repositoryConnector->getRelativePath($wc_url);
		$repository_url = $this->repositoryConnector->getRootUrl($wc_url);

		foreach ( $merged_revisions as $path => $revisions ) {
			$merged_messages = array();
			$revision_log = $this->revisionLogFactory->getRevisionLog($repository_url . $path);
			$ret .= PHP_EOL . $this->getCommitMessageHeading($path, $relative_path) . PHP_EOL;

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
	 * @param string $source_path Source path for merge operation.
	 * @param string $target_path Target path for merge operation.
	 *
	 * @return string
	 */
	protected function getCommitMessageHeading($source_path, $target_path)
	{
		$from_path = basename($source_path);
		$source_project = $this->repositoryConnector->getProjectUrl($source_path);

		$to_path = basename($target_path);
		$target_project = $this->repositoryConnector->getProjectUrl($target_path);

		if ( $source_project !== $target_project ) {
			$from_project_parts = explode('/', $source_project);
			$from_path .= ' (' . end($from_project_parts) . ')';
		}

		return 'Merging from ' . ucfirst($from_path) . ' to ' . ucfirst($to_path);
	}

}
