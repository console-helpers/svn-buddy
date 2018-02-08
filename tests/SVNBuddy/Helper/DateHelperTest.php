<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Helper;


use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{

	/**
	 * Container helper
	 *
	 * @var DateHelper
	 */
	protected $dateHelper;

	protected function setUp()
	{
		parent::setUp();

		$this->dateHelper = new DateHelper();
	}

	/**
	 * @dataProvider getTimeAgoDataProvider
	 */
	public function testGetTimeAgo($from_date, $to_date, $max_levels, $result)
	{
		$this->assertEquals($result, $this->dateHelper->getAgoTime($from_date, $to_date, $max_levels));
	}

	public function getTimeAgoDataProvider()
	{
		$from_date = mktime(0, 0, 0, 1, 1, 1970);

		return array(
			// Now.
			'now' => array($from_date, $from_date, 1, 'now'),

			// Exact intervals, 1 level .
			'exact, 1 level, 1 year' => array(strtotime('-1 year', $from_date), $from_date, 1, '1 year ago'),
			'exact, 1 level, 1 month' => array(strtotime('-1 month', $from_date), $from_date, 1, '1 month ago'),
			'exact, 1 level, 1 week' => array(strtotime('-1 week', $from_date), $from_date, 1, '1 week ago'),
			'exact, 1 level, 1 day' => array(strtotime('-1 day', $from_date), $from_date, 1, '1 day ago'),
			'exact, 1 level, 1 hour' => array(strtotime('-1 hour', $from_date), $from_date, 1, '1 hour ago'),
			'exact, 1 level, 1 minute' => array(strtotime('-1 minute', $from_date), $from_date, 1, '1 minute ago'),
			'exact, 1 level, 1 second' => array(strtotime('-1 second', $from_date), $from_date, 1, '1 second ago'),

			// 1 unit less intervals, 1 level.
			'1 unit less, 1 level, 1 year' => array(strtotime('-1 year 3 weeks', $from_date), $from_date, 1, '11 months ago'),
			'1 unit less, 1 level, 1 month' => array(strtotime('-1 month 1 day', $from_date), $from_date, 1, '4 weeks ago'),
			'1 unit less, 1 level, 1 week' => array(strtotime('-1 week 1 day', $from_date), $from_date, 1, '6 days ago'),
			'1 unit less, 1 level, 1 day' => array(strtotime('-1 day 1 hour', $from_date), $from_date, 1, '23 hours ago'),
			'1 unit less, 1 level, 1 hour' => array(strtotime('-1 hour 1 minute', $from_date), $from_date, 1, '59 minutes ago'),
			'1 unit less, 1 level, 1 minute' => array(strtotime('-1 minute 1 second', $from_date), $from_date, 1, '59 seconds ago'),

			// 1 unit less intervals, 2 levels.
			'1 second less, 2 levels, 1 year' => array(strtotime('-1 year 1 second', $from_date), $from_date, 2, '11 months 3 weeks ago'),
			'1 second less, 2 levels, 1 month' => array(strtotime('-1 month 1 second', $from_date), $from_date, 2, '4 weeks 2 days ago'),
			'1 second less, 2 levels, 1 week' => array(strtotime('-1 week 1 second', $from_date), $from_date, 2, '6 days 23 hours ago'),
			'1 second less, 2 levels, 1 day' => array(strtotime('-1 day 1 second', $from_date), $from_date, 2, '23 hours 59 minutes ago'),
			'1 second less, 2 levels, 1 hour' => array(strtotime('-1 hour 1 second', $from_date), $from_date, 2, '59 minutes 59 seconds ago'),
			'1 second less, 2 levels, 1 minute' => array(strtotime('-1 minute 1 second', $from_date), $from_date, 2, '59 seconds ago'),
			'1 second less, 2 levels, 1 second' => array(strtotime('-1 second', $from_date), $from_date, 2, '1 second ago'),
		);
	}

	public function testGetName()
	{
		$this->assertEquals('date', $this->dateHelper->getName());
	}

}
