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
	 * @param array  $revisions  Revisions.
	 * @param string $source_url Source URL.
	 *
	 * @return string
	 */
	protected function generateGroupBody(array $revisions, $source_url)
	{
		$merged_messages = array();
		$revision_log = $this->revisionLogFactory->getRevisionLog($source_url);
		$revisions_data = $revision_log->getRevisionsData('summary', $revisions);

		foreach ( $revisions as $revision ) {
			$merged_messages[] = ' * r' . $revision . ': ' . $revisions_data[$revision]['msg'];
		}

		$merged_messages = array_unique(array_map('trim', $merged_messages));

		return implode(PHP_EOL, $merged_messages);
	}

}
