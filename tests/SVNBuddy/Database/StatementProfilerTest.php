<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Database;


use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class StatementProfilerTest extends AbstractTestCase
{

	/**
	 * Statement profiler.
	 *
	 * @var StatementProfiler
	 */
	protected $statementProfiler;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->statementProfiler = new StatementProfiler();
	}

	public function testDisabledByDefault()
	{
		$this->assertFalse($this->statementProfiler->isActive());
	}

	public function testCanBeEnabled()
	{
		$this->statementProfiler->setActive(true);
		$this->assertTrue($this->statementProfiler->isActive());
	}

	public function testNoProfilesInitially()
	{
		$this->assertEmpty($this->statementProfiler->getProfiles());
	}

	public function testNoProfileCreatedWhenProfilerIsDisabled()
	{
		$this->statementProfiler->addProfile(0, 'perform', 'bb');

		$this->assertEmpty($this->statementProfiler->getProfiles());
	}

	public function testNoProfileIsRemovedWhenProfilerIsDisabled()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(0, 'perform', 'bb');
		$this->statementProfiler->setActive(false);
		$this->statementProfiler->removeProfile('bb');

		$this->assertCount(1, $this->statementProfiler->getProfiles());
	}

	/**
	 * @dataProvider profileAddingIgnoredDataProvider
	 */
	public function testProfileAddingIgnored($function, $statement, $profile_count)
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(0, $function, $statement);

		$this->assertCount($profile_count, $this->statementProfiler->getProfiles());
	}

	public static function profileAddingIgnoredDataProvider()
	{
		return array(
			'the "prepare" method call' => array('prepare', 'stmt', 0),
			'the empty statement' => array('perform', '', 0),
		);
	}

	public function testProfiledInformationIsCorrect()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));

		$actual_profiles = $this->statementProfiler->getProfiles();
		$this->assertCount(1, $actual_profiles);

		$this->assertEquals(
			array(
				'duration' => 5,
				'function' => 'perform',
				'statement' => 'bb',
				'bind_values' => array('cc' => 'dd'),
			),
			current($actual_profiles)
		);
	}

	public function testReset()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->assertCount(1, $this->statementProfiler->getProfiles());

		$this->statementProfiler->resetProfiles();
		$this->assertEmpty($this->statementProfiler->getProfiles());
	}

	public function testDuplicateStatementsTrackingRespectsBindParams()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('ee' => 'ff'));
		$this->assertCount(2, $this->statementProfiler->getProfiles());
	}

	public function testDuplicateStatementsTrackingEnabledByDefault()
	{
		$this->expectException('PDOException');
		$this->expectExceptionMessage('Duplicate statement:' . PHP_EOL . 'bb "dd"');

		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb :cc', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb :cc', array('cc' => 'dd'));
	}

	public function testDuplicateStatementsAreNotAllowed()
	{
		$this->expectException('PDOException');
		$this->expectExceptionMessage('Duplicate statement:' . PHP_EOL . 'bb "dd"');

		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb :cc', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb :cc', array('cc' => 'dd'));
	}

	public function testDuplicateStatementsAreAllowed()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(false);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));

		$this->assertTrue(true);
	}

	/**
	 * @dataProvider ignoredDuplicateStatementsAreRespectedDataProvider
	 */
	public function testIgnoredDuplicateStatementsAreRespected($statement, array $bind_values)
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->ignoreDuplicateStatement('IGNORE ' . PHP_EOL . ' ME');
		$this->statementProfiler->addProfile(5, 'perform', $statement, $bind_values);
		$this->statementProfiler->addProfile(5, 'perform', 'SELECT ...');

		$this->assertCount(2, $this->statementProfiler->getProfiles());
	}

	public static function ignoredDuplicateStatementsAreRespectedDataProvider()
	{
		return array(
			'normalized statement' => array('IGNORE ME', array()),
			'non-normalized statement' => array('IGNORE ME' . PHP_EOL, array()),
			'statement with bind values' => array('IGNORE ME', array('cc' => 'dd')),
		);
	}

	public function testIgnoredDuplicateStatementsAppearInVerboseOutput()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(true)->shouldBeCalled();
		$io
			->writeln(array(
				'',
				'<debug>[db, 5s]: IGNORE ME "bb"</debug>',
				'<debug>[db origin]: ' . __FILE__ . ':199</debug>',
			))
			->shouldBeCalled();
		$this->statementProfiler->setIO($io->reveal());

		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->ignoreDuplicateStatement('IGNORE ME :aa');
		$this->statementProfiler->addProfile(5, 'perform', 'IGNORE ME :aa', array('aa' => 'bb'));
	}

	public function testDuplicateStatementRemoval()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->removeProfile('bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));

		$this->assertTrue(true);
	}

	public function testVerboseOutput()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(true)->shouldBeCalled();

		if ( PHP_VERSION_ID >= 80200 ) {
			// Adjust for PHP 8.2+ multi-line statement handling in traces.
			$expect_line = 241;
		}
		elseif ( PHP_VERSION_ID >= 70000 ) {
			// Adjust for PHP 7.0+ multi-line statement handling in traces.
			$expect_line = 245;
		}
		else {
			// Adjust for PHP 5.0+ multi-line statement handling in traces.
			$expect_line = 246;
		}

		$io
			->writeln(array(
				'',
				'<debug>[db, 5s]: SELECT "PA" "PAR","AM"</debug>',
				'<debug>[db origin]: ' . __FILE__ . ':' . $expect_line . '</debug>',
			))
			->shouldBeCalled();
		$this->statementProfiler->setIO($io->reveal());

		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile( // 241
			5,
			'perform',
			'SELECT :pa :param',
			array('pa' => 'PA', 'param' => array('PAR', 'AM')) // 245
		); // 246
		$this->assertCount(1, $this->statementProfiler->getProfiles());
	}

	public function testNonVerboseOutput()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(false)->shouldBeCalled();
		$io->writeln(Argument::cetera())->shouldNotBeCalled();
		$this->statementProfiler->setIO($io->reveal());

		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->assertCount(1, $this->statementProfiler->getProfiles());
	}

	public function testGetLogger()
	{
		$this->assertInstanceOf(LoggerInterface::class, $this->statementProfiler->getLogger());
	}

	public function testLogLevel()
	{
		$this->statementProfiler->setLogLevel(LogLevel::ERROR);
		$this->assertEquals(LogLevel::ERROR, $this->statementProfiler->getLogLevel());
	}

	public function testLogFormat()
	{
		$this->statementProfiler->setLogFormat('test');
		$this->assertEquals('test', $this->statementProfiler->getLogFormat());
	}

}

