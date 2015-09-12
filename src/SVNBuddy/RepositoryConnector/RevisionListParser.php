<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\RepositoryConnector;


class RevisionListParser
{

	/**
	 * Expands ranges in revision list.
	 *
	 * @param array  $revisions       Revisions.
	 * @param string $range_separator Range separator.
	 *
	 * @return array
	 * @throws \InvalidArgumentException When inverted range is used.
	 * @throws \InvalidArgumentException When invalid revision is used.
	 */
	public function expandRanges(array $revisions, $range_separator = '-')
	{
		// Since SVN 1.7+ there can be "*" at the end of merged revision or revision range.
		$ret = array();
		$range_regexp = '/^([\d]+)' . preg_quote($range_separator, '/') . '([\d]+)\*?$/';

		foreach ( $revisions as $raw_revision ) {
			if ( preg_match($range_regexp, $raw_revision, $regs) ) {
				$range_start = (int)$regs[1];
				$range_end = (int)$regs[2];

				if ( $range_start > $range_end ) {
					throw new \InvalidArgumentException(
						'Inverted revision range "' . $raw_revision . '" is not implemented.'
					);
				}

				for ( $i = $range_start; $i <= $range_end; $i++ ) {
					$ret[$i] = true;
				}
			}
			elseif ( preg_match('/^([\d]+)\*?$/', $raw_revision, $regs) ) {
				$ret[(int)$regs[1]] = true;
			}
			else {
				throw new \InvalidArgumentException('The "' . $raw_revision . '" revision is invalid.');
			}
		}

		return array_keys($ret);
	}

	/**
	 * Creates ranges from given revision list.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return array
	 */
	public function createRanges(array $revisions)
	{
		$ret = array();
		sort($revisions, SORT_NUMERIC);

		foreach ( $revisions as $revision ) {
			$ret[] = array($revision, $revision);
		}

		return $ret;
	}

}
