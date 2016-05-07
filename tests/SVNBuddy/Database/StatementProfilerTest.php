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


use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class StatementProfilerTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Statement profiler.
	 *
	 * @var StatementProfiler
	 */
	protected $statementProfiler;

	protected function setUp()
	{
		parent::setUp();

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
		$this->statementProfiler->addProfile(0, 'aa', 'bb');

		$this->assertCount(0, $this->statementProfiler->getProfiles());
	}

	/**
	 * @dataProvider profileAddingIgnoredDataProvider
	 */
	public function testProfileAddingIgnored($function, $statement, array $bind_values, $profile_count)
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(0, $function, $statement, $bind_values);

		$this->assertCount($profile_count, $this->statementProfiler->getProfiles());
	}

	public function profileAddingIgnoredDataProvider()
	{
		return array(
			'the "prepare" method call' => array('prepare', 'stmt', array(), 0),
			'the empty statement' => array('perform', '', array(), 0),
			'ignored statement 1' => array('perform', 'SELECT LastRevision FROM PluginData WHERE Name = :name', array(), 0),
			'ignored statement 2' => array('perform', 'SELECT Id FROM Projects WHERE Path = :path', array(), 0),
		);
	}

	public function testProfiledInformationIsCorrect()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'aa', 'bb', array('cc' => 'dd'));

		$actual_profiles = $this->statementProfiler->getProfiles();
		$this->assertCount(1, $actual_profiles);

		$this->assertEquals(
			array(
				'duration' => 5,
				'function' => 'aa',
				'statement' => 'bb',
				'bind_values' => array('cc' => 'dd'),
			),
			current($actual_profiles)
		);
	}

	public function testReset()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'aa', 'bb', array('cc' => 'dd'));
		$this->assertCount(1, $this->statementProfiler->getProfiles());

		$this->statementProfiler->resetProfiles();
		$this->assertEmpty($this->statementProfiler->getProfiles());
	}

	public function testDuplicateStatementsWithDifferentParamsAllowed()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'aa', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'aa', 'bb', array('ee' => 'ff'));
		$this->assertCount(2, $this->statementProfiler->getProfiles());
	}

	/**
	 * @expectedException \PDOException
	 * @expectedExceptionMessage Duplicate statement:
	 */
	public function testDuplicateStatementsAreNotAllowed()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->trackDuplicates(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
	}

	public function testDuplicateStatementsAreAllowed()
	{
		$this->statementProfiler->setActive(true);
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));
		$this->statementProfiler->addProfile(5, 'perform', 'bb', array('cc' => 'dd'));

		$this->assertTrue(true);
	}

	public function testVerboseOutput()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(true)->shouldBeCalled();
		$io->writeln(array('', '<debug>[db, 5s]: bb</debug>'))->shouldBeCalled();

		$profiler = new StatementProfiler($io->reveal());

		$profiler->setActive(true);
		$profiler->addProfile(5, 'aa', 'bb', array('cc' => 'dd'));
		$this->assertCount(1, $profiler->getProfiles());
	}

	public function testNonVerboseOutput()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(false)->shouldBeCalled();
		$io->writeln(Argument::cetera())->shouldNotBeCalled();

		$profiler = new StatementProfiler($io->reveal());

		$profiler->setActive(true);
		$profiler->addProfile(5, 'aa', 'bb', array('cc' => 'dd'));
		$this->assertCount(1, $profiler->getProfiles());
	}

}

