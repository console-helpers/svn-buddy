<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Command;


class MergeCommandTest extends AbstractCommandTestCase
{

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->commandName = 'merge';

		parent::setupTest();
	}

	public function testExampleTest()
	{
		$this->markTestIncomplete('TODO');
	}

}
