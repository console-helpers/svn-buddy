<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\DatabaseMigration;


use Aura\Sql\Profiler;
use ConsoleHelpers\DatabaseMigration\MigrationContext;
use ConsoleHelpers\DatabaseMigration\MigrationManager;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\Database\AbstractDatabaseAwareTestCase;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\RegExToken;

class MigrationManagerTest extends AbstractDatabaseAwareTestCase
{

	/**
	 * Container.
	 *
	 * @var \ArrayAccess
	 */
	protected $container;

	/**
	 * Migration manager context.
	 *
	 * @var MigrationContext
	 */
	protected $context;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->prophesize('ArrayAccess')->reveal();
		$this->context = new MigrationContext($this->database);
	}

	protected function tearDown()
	{
		parent::tearDown();

		if ( strpos($this->getName(false), 'testCreateMigration') === 0 ) {
			$this->deleteTempMigrations();
		}
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "non-existing" does not exist or not a directory.
	 */
	public function testMissingMigrationsDirectory()
	{
		new MigrationManager('non-existing', $this->container);
	}

	public function testMigrationsDirectoryIsAFile()
	{
		$this->setExpectedException(
			'InvalidArgumentException',
			'The "' . __FILE__ . '" does not exist or not a directory.'
		);

		new MigrationManager(__FILE__, $this->container);
	}

	public function testGetMigrationFileExtensions()
	{
		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('one')->reveal());
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('two')->reveal());

		$this->assertEquals(array('one', 'two'), $manager->getMigrationFileExtensions());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage No migrations runners registered.
	 */
	public function testGetMigrationFileExtensionsEmpty()
	{
		$manager = $this->getMigrationManager('migrations-none');

		$manager->getMigrationFileExtensions();
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The migration name can consist only from alpha-numeric characters, as well as dots and underscores.
	 *
	 * @dataProvider createMigrationWithInvalidNameDataProvider
	 */
	public function testCreateMigrationWithInvalidName($migration_name)
	{
		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('one')->reveal());

		$manager->createMigration($migration_name, 'one');
	}

	public function createMigrationWithInvalidNameDataProvider()
	{
		return array(
			array(' '),
			array('-'),
			array('A'),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The migration runner for "invalid" file extension is not registered.
	 */
	public function testCreateMigrationWithInvalidFileExtension()
	{
		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('one')->reveal());

		$manager->createMigration('name', 'invalid');
	}

	public function testRunNoMigrations()
	{
		$this->assertEmpty($this->getMigrationsTableSchema(), 'No "Migrations" table initially.');

		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('one')->reveal());

		$manager->run($this->context); // Will create "Migrations" table.
		$manager->run($this->context); // Won't re-create "Migrations" table.

		$this->assertSame(
			'CREATE TABLE "Migrations" (
					"Name" TEXT(255,0) NOT NULL,
					"ExecutedOn" INTEGER NOT NULL,
					PRIMARY KEY("Name")
				)',
			$this->getMigrationsTableSchema(),
			'The "Migrations" table was created.'
		);
	}

	public function testCreateMigrationSuccess()
	{
		$runner = $this->createMigrationRunnerMock('one');
		$runner->getTemplate()->willReturn('tpl content')->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($runner->reveal());

		$migration_name = $manager->createMigration('test', 'one');
		$this->assertEquals(date('Ymd_Hi') . '_test.one', $migration_name);

		$migration_filename = $this->getMigrationsFolder('migrations-none') . '/' . $migration_name;
		$this->assertFileExists($migration_filename);

		$this->assertEquals('tpl content', file_get_contents($migration_filename));
	}

	public function testCreateMigrationDuplicate()
	{
		$runner = $this->createMigrationRunnerMock('one');
		$runner->getTemplate()->willReturn('tpl content')->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($runner->reveal());

		$migration_name = $manager->createMigration('test', 'one');

		$this->setExpectedException('LogicException', 'The migration file "' . $migration_name . '" already exists.');
		$manager->createMigration('test', 'one');
	}

	public function testRunSorting()
	{
		$run_order = array();

		$runner1 = $this->createMigrationRunnerMock('one');
		$runner1
			->run(new RegExToken('#/.*\.one$#'), $this->context)
			->will(function (array $args) use (&$run_order) {
				$run_order[] = basename($args[0]);
			})
			->shouldBeCalled();

		$runner2 = $this->createMigrationRunnerMock('two');
		$runner2
			->run(new RegExToken('#/.*\.two#'), $this->context)
			->will(function (array $args) use (&$run_order) {
				$run_order[] = basename($args[0]);
			})
			->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-two-types');
		$manager->registerMigrationRunner($runner1->reveal());
		$manager->registerMigrationRunner($runner2->reveal());

		$time_now = (string)time();
		$manager->run($this->context);

		$this->assertTableContent(
			'Migrations',
			array(
				array('Name' => 'a.one', 'ExecutedOn' => $time_now),
				array('Name' => 'a.two', 'ExecutedOn' => $time_now),
				array('Name' => 'b.one', 'ExecutedOn' => $time_now),
			)
		);

		$this->assertSame(array('a.one', 'a.two', 'b.one'), $run_order);
	}

	public function testRunMigrationIsExecutedOnce()
	{
		$runner = $this->createMigrationRunnerMock('one');
		$runner->run(new RegExToken('#/a\.one$#'), $this->context)->shouldBeCalledTimes(1);

		$manager = $this->getMigrationManager('migrations-one-type');
		$manager->registerMigrationRunner($runner->reveal());

		$manager->run($this->context); // Executes migrations.
		$manager->run($this->context); // Won't execute migration again.
	}

	public function testRunMigrationFromUnknownRunnersAreIgnored()
	{
		$runner = $this->createMigrationRunnerMock('one');
		$runner->run(new RegExToken('#/a\.one$#'), $this->context)->shouldBeCalledTimes(1);
		$runner->run(new RegExToken('#/b\.one$#'), $this->context)->shouldBeCalledTimes(1);

		$manager = $this->getMigrationManager('migrations-two-types');
		$manager->registerMigrationRunner($runner->reveal());

		$time_now = (string)time();
		$manager->run($this->context);

		$this->assertTableContent(
			'Migrations',
			array(
				array('Name' => 'a.one', 'ExecutedOn' => $time_now),
				array('Name' => 'b.one', 'ExecutedOn' => $time_now),
			)
		);
	}

	public function testRunRemovedMigrationsAreDeleted()
	{
		$runner = $this->createMigrationRunnerMock('one');
		$runner->run(new RegExToken('#/.*\.one$#'), $this->context)->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-one-type');
		$manager->registerMigrationRunner($runner->reveal());

		$migrations_folder = $this->getMigrationsFolder('migrations-one-type');
		copy($migrations_folder . '/a.one', $migrations_folder . '/b.one');

		$time_now = (string)time();
		$manager->run($this->context);

		unlink($migrations_folder . '/b.one');

		$this->assertTableContent(
			'Migrations',
			array(
				array('Name' => 'a.one', 'ExecutedOn' => $time_now),
				array('Name' => 'b.one', 'ExecutedOn' => $time_now),
			)
		);

		$manager->run($this->context);

		$this->assertTableContent(
			'Migrations',
			array(
				array('Name' => 'a.one', 'ExecutedOn' => $time_now),
			)
		);
	}

	public function testRunSetsContainerToContext()
	{
		$context = $this->prophesize('ConsoleHelpers\DatabaseMigration\MigrationContext');
		$context->setContainer($this->container)->shouldBeCalled();
		$context->getDatabase()->willReturn($this->database)->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-none');
		$manager->registerMigrationRunner($this->createMigrationRunnerMock('one')->reveal());

		$manager->run($context->reveal());
	}

	public function testRunResetsProfilerAfterCompletion()
	{
		$profiler = new Profiler();
		$profiler->setActive(true);
		$this->database->setProfiler($profiler);

		$runner = $this->createMigrationRunnerMock('one');
		$runner
			->run(Argument::cetera())
			->will(function (array $args) {
				$args[1]->getDatabase()->perform('SELECT * FROM sqlite_master');
			})
			->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-one-type');
		$manager->registerMigrationRunner($runner->reveal());

		$manager->run($this->context);

		$this->assertEmpty($profiler->getProfiles());
	}

	public function testRunDuplicateStatementsAreAllowed()
	{
		$profiler = new StatementProfiler();
		$profiler->setActive(true);
		$this->database->setProfiler($profiler);

		$runner = $this->createMigrationRunnerMock('one');
		$runner
			->run(Argument::cetera())
			->will(function (array $args) {
				$args[1]->getDatabase()->perform('SELECT * FROM sqlite_master');
				$args[1]->getDatabase()->perform('SELECT * FROM sqlite_master');
			})
			->shouldBeCalled();

		$manager = $this->getMigrationManager('migrations-one-type');
		$manager->registerMigrationRunner($runner->reveal());

		$manager->run($this->context);

		$this->assertEmpty($profiler->getProfiles());
	}

	/**
	 * Get migrations table schema.
	 *
	 * @return string
	 */
	protected function getMigrationsTableSchema()
	{
		$sql = 'SELECT sql
				FROM sqlite_master
				WHERE type = :type AND name = :name';

		return $this->database->fetchValue($sql, array(
			'type' => 'table',
			'name' => 'Migrations',
		));
	}

	/**
	 * Deletes all migrations from "migrations-none" folder.
	 *
	 * @return void
	 */
	protected function deleteTempMigrations()
	{
		$migrations_folder = $this->getMigrationsFolder('migrations-none');
		$temp_files = glob($migrations_folder . '/*.one');
		array_map('unlink', $temp_files);
	}

	/**
	 * Creates migration runner mock.
	 *
	 * @param string $file_extension File extension.
	 *
	 * @return ObjectProphecy
	 */
	protected function createMigrationRunnerMock($file_extension)
	{
		$runner = $this->prophesize('ConsoleHelpers\DatabaseMigration\AbstractMigrationRunner');
		$runner->getFileExtension()->willReturn($file_extension)->shouldBeCalled();

		return $runner;
	}

	/**
	 * Returns fixture folder name.
	 *
	 * @param string $scenario Scenario.
	 *
	 * @return MigrationManager
	 */
	protected function getMigrationManager($scenario)
	{
		return new MigrationManager($this->getMigrationsFolder($scenario), $this->container);
	}

	/**
	 * Returns migration folder by scenario.
	 *
	 * @param string $scenario Scenario.
	 *
	 * @return string
	 */
	protected function getMigrationsFolder($scenario)
	{
		return __DIR__ . '/fixtures/' . $scenario;
	}

}
