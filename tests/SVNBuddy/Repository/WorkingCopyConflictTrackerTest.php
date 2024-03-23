<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Command\ConflictsCommand;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class WorkingCopyConflictTrackerTest extends TestCase
{

	use ExpectException;

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	/**
	 * Command config.
	 *
	 * @var ObjectProphecy
	 */
	protected $commandConfig;

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	protected $workingCopyConflictTracker;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');
		$this->commandConfig = $this->prophesize('\ConsoleHelpers\SVNBuddy\Config\CommandConfig');

		$this->workingCopyConflictTracker = new WorkingCopyConflictTracker(
			$this->repositoryConnector->reveal(),
			$this->commandConfig->reveal()
		);
	}

	/**
	 * @dataProvider addDataProvider
	 */
	public function testAdd(array $recorded_conflicts, array $new_conflicts, array $expected_conflicts)
	{
		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path, $recorded_conflicts);
		$this->repositoryConnector->getWorkingCopyConflicts($wc_path)->willReturn($new_conflicts);

		$this->assertEquals($recorded_conflicts, $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));

		$this->workingCopyConflictTracker->add($wc_path);

		$this->assertEquals($expected_conflicts, $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));
	}

	public static function addDataProvider()
	{
		return array(
			array(array(), array('new-file.txt'), array('new-file.txt')),
			array(array('old-file.txt'), array('new-file.txt'), array('new-file.txt', 'old-file.txt')),
		);
	}

	public function testAddError()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The working copy at "/path/to/working-copy" has no conflicts to be added.');

		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path);
		$this->repositoryConnector->getWorkingCopyConflicts($wc_path)->willReturn(array());

		$this->workingCopyConflictTracker->add($wc_path);
	}

	/**
	 * @dataProvider replaceDataProvider
	 */
	public function testReplace(array $recorded_conflicts, array $new_conflicts, array $expected_conflicts)
	{
		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path, $recorded_conflicts);
		$this->repositoryConnector->getWorkingCopyConflicts($wc_path)->willReturn($new_conflicts);

		$this->assertEquals($recorded_conflicts, $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));

		$this->workingCopyConflictTracker->replace($wc_path);

		$this->assertEquals($expected_conflicts, $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));
	}

	public static function replaceDataProvider()
	{
		return array(
			array(array(), array('new-file.txt'), array('new-file.txt')),
			array(array('old-file.txt'), array('new-file.txt'), array('new-file.txt')),
		);
	}

	public function testReplaceError()
	{
		$this->expectException('\LogicException');
		$this->expectExceptionMessage('The working copy at "/path/to/working-copy" has no conflicts to be added.');

		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path);
		$this->repositoryConnector->getWorkingCopyConflicts($wc_path)->willReturn(array());

		$this->workingCopyConflictTracker->replace($wc_path);
	}

	public function testErase()
	{
		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path, array('old-file.txt'));

		$this->assertEquals(array('old-file.txt'), $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));

		$this->workingCopyConflictTracker->erase($wc_path);

		$this->assertEmpty($this->workingCopyConflictTracker->getRecordedConflicts($wc_path));
	}

	public function testGetNewConflicts()
	{
		$expected = array('new-file.txt');
		$wc_path = '/path/to/working-copy';

		$this->repositoryConnector->getWorkingCopyConflicts($wc_path)->willReturn($expected);

		$this->assertEquals($expected, $this->workingCopyConflictTracker->getNewConflicts($wc_path));
	}

	public function testGetRecordedConflicts()
	{
		$wc_path = '/path/to/working-copy';

		$this->expectRecordedConflicts($wc_path, array('old-file.txt'));

		$this->assertEquals(array('old-file.txt'), $this->workingCopyConflictTracker->getRecordedConflicts($wc_path));
	}

	/**
	 * Expects recorded config setting access.
	 *
	 * @param string $wc_path        Working copy path.
	 * @param array  $existing_value Existing value.
	 *
	 * @return void
	 */
	protected function expectRecordedConflicts($wc_path, array $existing_value = array())
	{
		$this->commandConfig
			->getSettingValue(
				ConflictsCommand::SETTING_CONFLICTS_RECORDED_CONFLICTS,
				Argument::type('ConsoleHelpers\SVNBuddy\Command\ConflictsCommand'),
				$wc_path
			)
			->willReturn($existing_value);

		$this->commandConfig
			->setSettingValue(
				ConflictsCommand::SETTING_CONFLICTS_RECORDED_CONFLICTS,
				Argument::type('ConsoleHelpers\SVNBuddy\Command\ConflictsCommand'),
				$wc_path,
				Argument::type('array')
			)
			->will(function ($args, $object) {
				$object->getSettingValue($args[0], $args[1], $args[2])->willReturn($args[3]);
			});
	}

}
