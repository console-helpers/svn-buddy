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


use ConsoleHelpers\SVNBuddy\Database\MigrationManager;
use ConsoleHelpers\SVNBuddy\Database\MigrationManagerContext;
use Pimple\Container;
use Prophecy\Argument;

class MigrationManagerTest extends AbstractDatabaseAwareTestCase
{

	/**
	 * Container.
	 *
	 * @var Container
	 */
	protected $container;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->prophesize('Pimple\Container')->reveal();

		if ( $this->getName(false) === 'testRunSorting' ) {
			$migrations_folder = $this->getMigrationsFolder('migrations-sorting');

			// Fixtures listed in reverse alphabetical order on purpose.
			$fixtures = array('c.php', 'b.sql', 'a.sql');

			foreach ( $fixtures as $fixture ) {
				copy($migrations_folder . '/' . $fixture . '.tpl', $migrations_folder . '/' . $fixture);
			}
		}
	}

	protected function tearDown()
	{
		parent::tearDown();

		if ( $this->getName(false) === 'testRunSorting' ) {
			$migrations_folder = $this->getMigrationsFolder('migrations-sorting');

			// Fixtures listed in reverse alphabetical order on purpose.
			$fixtures = array('c.php', 'b.sql', 'a.sql');

			foreach ( $fixtures as $fixture ) {
				unlink($migrations_folder . '/' . $fixture);
			}
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

	public function testRunNoMigrations()
	{
		$this->assertEmpty($this->getMigrationsTableSchema(), 'No "Migrations" table initially.');

		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('no-migrations');

		$manager->run($context); // Will create "Migrations" table.
		$manager->run($context); // Won't re-create "Migrations" table.

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

	public function testRunSorting()
	{
		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('migrations-sorting');

		$time_now = (string)time();
		$manager->run($context);

		$this->assertTableContent(
			'Migrations',
			array(
				array(
					'Name' => 'a.sql',
					'ExecutedOn' => $time_now,
				),
				array(
					'Name' => 'b.sql',
					'ExecutedOn' => $time_now,
				),
				array(
					'Name' => 'c.php',
					'ExecutedOn' => $time_now,
				),
			)
		);

		$this->assertTableContent(
			'SampleTable',
			array(
				array('Title' => 'Test 1'),
				array('Title' => 'Test 2'),
			)
		);
	}

	public function testRunMigrationIsExecutedOnce()
	{
		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('migrations-executed-once');

		$manager->run($context); // Executes migrations.
		$manager->run($context); // Won't execute migration again.

		$this->assertTableEmpty('SampleTable');
	}

	public function testRunRemovedMigrationsAreDeleted()
	{
		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('migrations-removal');

		$migrations_folder = $this->getMigrationsFolder('migrations-removal');
		copy($migrations_folder . '/a.sql', $migrations_folder . '/b.sql');

		$time_now = (string)time();
		$manager->run($context);

		unlink($migrations_folder . '/b.sql');

		$this->assertTableContent(
			'Migrations',
			array(
				array(
					'Name' => 'a.sql',
					'ExecutedOn' => $time_now,
				),
				array(
					'Name' => 'b.sql',
					'ExecutedOn' => $time_now,
				),
			)
		);

		$manager->run($context);

		$this->assertTableContent(
			'Migrations',
			array(
				array(
					'Name' => 'a.sql',
					'ExecutedOn' => $time_now,
				),
			)
		);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "a.sql" migration contains no SQL statements.
	 */
	public function testRunEmptySQLMigration()
	{
		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('migrations-sql-empty');

		$manager->run($context);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "a.php" migration doesn't return a closure.
	 */
	public function testRunMalformedPHPMigration()
	{
		$context = new MigrationManagerContext($this->database);
		$manager = $this->getMigrationManager('migrations-php-empty');

		$manager->run($context);
	}

	public function testRunSetsContainerToContext()
	{
		$context = $this->prophesize('ConsoleHelpers\SVNBuddy\Database\MigrationManagerContext');
		$context->setContainer($this->container)->shouldBeCalled();
		$context->getDatabase()->willReturn($this->database)->shouldBeCalled();

		$manager = $this->getMigrationManager('no-migrations');

		$manager->run($context->reveal());
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
