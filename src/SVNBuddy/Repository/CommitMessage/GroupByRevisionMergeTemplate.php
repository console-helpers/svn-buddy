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


class GroupByRevisionMergeTemplate extends AbstractGroupByMergeTemplate
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
	 * Builds group body.
	 *
	 * @param string $path           Path.
	 * @param array  $revisions      Revisions.
	 * @param string $repository_url Repository URL.
	 * @param string $relative_path  Relative path.
	 *
	 * @return string
	 */
	protected function generateGroupBody($path, array $revisions, $repository_url, $relative_path)
	{
		$merged_messages = array();
		$revision_log = $this->revisionLogFactory->getRevisionLog($repository_url . $path);
		$revisions_data = $revision_log->getRevisionsData('summary', $revisions);

		foreach ( $revisions as $revision ) {
			$merged_messages[] = ' * r' . $revision . ': ' . $revisions_data[$revision]['msg'];
		}

		$merged_messages = array_unique(array_map('trim', $merged_messages));

		if ( count($revisions) > 1 ) {
			$ret = '';
			$ret .= $this->generateGroupHeading($path, $relative_path) . PHP_EOL;
			$ret .= implode(PHP_EOL, $merged_messages);

			return $ret;
		}

		$ret = '';
		$ret .= $this->generateGroupHeading($path, $relative_path, false) . ': ';
		$ret .= implode(PHP_EOL, $merged_messages);

		return $ret;
	}

}
