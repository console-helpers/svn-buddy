<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Helper;


use Symfony\Component\Console\Helper\Helper;

class DateHelper extends Helper
{

	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return 'date';
	}

	/**
	 * Returns time passed between 2 given dates in "X minutes Y seconds ago" format
	 *
	 * @param integer $from_date  From date.
	 * @param integer $to_date    To date.
	 * @param integer $max_levels Max levels.
	 *
	 * @return string
	 */
	public function getAgoTime($from_date, $to_date = null, $max_levels = 1)
	{
		$blocks = array(
			array('name' => 'year', 'amount' => 60 * 60 * 24 * 365),
			array('name' => 'month', 'amount' => 60 * 60 * 24 * 31),
			array('name' => 'week', 'amount' => 60 * 60 * 24 * 7),
			array('name' => 'day', 'amount' => 60 * 60 * 24),
			array('name' => 'hour', 'amount' => 60 * 60),
			array('name' => 'minute', 'amount' => 60),
			array('name' => 'second', 'amount' => 1),
		);

		// @codeCoverageIgnoreStart
		if ( !isset($to_date) ) {
			$to_date = time();
		}
		// @codeCoverageIgnoreEnd

		$diff = abs($to_date - $from_date);

		if ( $diff == 0 ) {
			return 'now';
		}

		$current_level = 1;
		$result = array();

		foreach ( $blocks as $block ) {
			if ( $current_level > $max_levels ) {
				break;
			}

			if ( $diff / $block['amount'] >= 1 ) {
				$amount = floor($diff / $block['amount']);
				$plural = $amount > 1 ? 's' : '';

				$result[] = $amount . ' ' . $block['name'] . $plural;
				$diff -= $amount * $block['amount'];
				$current_level++;
			}
		}

		return implode(' ', $result) . ' ago';
	}

}
