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


abstract class AbstractGroupByMergeTemplate extends AbstractMergeTemplate
{

	/**
	 * Applies merge template to a working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	public function apply($wc_path)
	{
		$ret = array();
		$merged_revisions = $this->repositoryConnector->getMergedRevisionChanges($wc_path, true);
		$unmerged_revisions = $this->repositoryConnector->getMergedRevisionChanges($wc_path, false);
		$has_merged_revisions = $this->flattenMergedRevisions($merged_revisions);
		$has_unmerged_revisions = $this->flattenMergedRevisions($unmerged_revisions);

		if ( !$has_merged_revisions && !$has_unmerged_revisions ) {
			return '';
		}

		$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
		$relative_path = $this->repositoryConnector->getRelativePath($wc_url);
		$repository_url = $this->repositoryConnector->getRootUrl($wc_url);

		if ( $has_merged_revisions ) {
			$ret[] = $this->doGenerate($merged_revisions, $relative_path, $repository_url, true);
		}

		if ( $has_unmerged_revisions ) {
			$ret[] = $this->doGenerate($unmerged_revisions, $relative_path, $repository_url, false);
		}

		return \implode(\PHP_EOL . \PHP_EOL, $ret);
	}

	/**
	 * Builds commit message.
	 *
	 * @param array   $merged_revisions Merged revisions.
	 * @param string  $relative_path    Relative path.
	 * @param string  $repository_url   Repository url.
	 * @param boolean $merge_direction  Merge direction.
	 *
	 * @return string
	 */
	protected function doGenerate(array $merged_revisions, $relative_path, $repository_url, $merge_direction)
	{
		$ret = '';

		foreach ( $merged_revisions as $path => $revisions ) {
			$ret .= PHP_EOL;
			$ret .= $this->generateGroupBody($path, $revisions, $repository_url, $relative_path, $merge_direction);
			$ret .= PHP_EOL;
		}

		return trim($ret);
	}

	/**
	 * Builds group heading.
	 *
	 * @param string  $source_path     Source path for merge operation.
	 * @param string  $target_path     Target path for merge operation.
	 * @param boolean $merge_direction Merge direction.
	 * @param boolean $long            Generate long heading.
	 *
	 * @return string
	 */
	protected function generateGroupHeading($source_path, $target_path, $merge_direction, $long = true)
	{
		$from_path = basename($source_path);
		$source_project = $this->repositoryConnector->getProjectUrl($source_path);

		$to_path = basename($target_path);
		$target_project = $this->repositoryConnector->getProjectUrl($target_path);

		if ( $source_project !== $target_project ) {
			$from_project_parts = explode('/', $source_project);
			$from_path .= ' (' . end($from_project_parts) . ')';
		}

		if ( $long ) {
			return sprintf(
				'%s from %s to %s',
				$merge_direction ? 'Merging' : 'Reverse-merging',
				ucfirst($from_path),
				ucfirst($to_path)
			);
		}

		return sprintf(
			'%s (%s > %s)',
			$merge_direction ? 'Merge' : 'Reverse-merge',
			$from_path,
			$to_path
		);
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
	abstract protected function generateGroupBody(
		$path,
		array $revisions,
		$repository_url,
		$relative_path,
		$merge_direction
	);

}
