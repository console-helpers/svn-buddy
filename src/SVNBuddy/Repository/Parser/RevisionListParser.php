<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\Parser;


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
	 * Collapses ranges.
	 *
	 * @param array  $revisions       Revisions.
	 * @param string $range_separator Range separator.
	 *
	 * @return array
	 */
	public function collapseRanges(array $revisions, $range_separator = '-')
	{
		sort($revisions, SORT_NUMERIC);
		$revisions = array_map('intval', $revisions);

		$ret = array();
		$range_start = $range_end = null;

		foreach ( $revisions as $revision ) {
			// New range detected.
			if ( $range_start === null ) {
				$range_start = $range_end = $revision;
				continue;
			}

			// Expanding existing range.
			if ( $range_end + 1 === $revision ) {
				$range_end = $revision;
				continue;
			}

			$ret[] = $range_start === $range_end ? $range_start : $range_start . $range_separator . $range_end;
			$range_start = $range_end = $revision;
		}

		if ( $range_start !== null && $range_end !== null ) {
			$ret[] = $range_start === $range_end ? $range_start : $range_start . $range_separator . $range_end;
		}

		return $ret;
	}

}
