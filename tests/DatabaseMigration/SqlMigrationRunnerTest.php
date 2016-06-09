<?php
/**
 * This file is part of the DB-Migration library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/db-migration
 */

namespace Tests\ConsoleHelpers\DatabaseMigration;


use ConsoleHelpers\DatabaseMigration\AbstractMigrationRunner;
use ConsoleHelpers\DatabaseMigration\SqlMigrationRunner;
use Prophecy\Argument;

class SqlMigrationRunnerTest extends AbstractMigrationRunnerTest
{

	public function testGetFileExtension()
	{
		$this->assertEquals('sql', $this->runner->getFileExtension());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "empty-migration.sql" migration contains no SQL statements.
	 */
	public function testRunEmptySQLMigration()
	{
		$this->runner->run($this->getFixture('empty-migration.sql'), $this->context->reveal());
	}

	public function testRun()
	{
		$sequence = array();

		$this->database
			->perform(Argument::any())
			->will(function (array $args) use (&$sequence) {
				$sequence[] = $args[0];
			})
			->shouldBeCalled();

		$this->runner->run($this->getFixture('non-empty-migration.sql'), $this->context->reveal());

		$this->assertSame(array('SELECT 1', 'SELECT 2'), $sequence);
	}

	public function testGetTemplate()
	{
		$this->assertEmpty($this->runner->getTemplate());
	}

	/**
	 * Creates migration type.
	 *
	 * @return AbstractMigrationRunner
	 */
	protected function createMigrationRunner()
	{
		return new SqlMigrationRunner();
	}

}
