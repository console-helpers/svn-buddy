<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Helper;


use Symfony\Component\Console\Helper\Helper;

class OutputHelper extends Helper
{

	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return 'output';
	}

	/**
	 * Returns formatted list of records.
	 *
	 * @param array       $items         List of items.
	 * @param integer     $items_per_row Number of bugs displayed per row.
	 * @param string|null $color         Color.
	 *
	 * @return string
	 */
	public function formatArray(array $items, $items_per_row, $color = null)
	{
		$items_chunks = array_chunk($items, $items_per_row);

		$ret = array();

		if ( isset($color) ) {
			foreach ( $items_chunks as $item_chunk ) {
				$ret[] = '<fg=' . $color . '>' . implode('</>, <fg=' . $color . '>', $item_chunk) . '</>';
			}
		}
		else {
			foreach ( $items_chunks as $item_chunk ) {
				$ret[] = implode(', ', $item_chunk);
			}
		}

		return implode(',' . PHP_EOL, $ret);
	}

}
