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


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Database\MigrationManagerContext;
use Pimple\Container;
use Prophecy\Argument;

class MigrationManagerContextTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	protected $database;

	/**
	 * Container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Context.
	 *
	 * @var MigrationManagerContext
	 */
	protected $context;

	protected function setUp()
	{
		parent::setUp();

		$this->database = $this->prophesize('Aura\Sql\ExtendedPdoInterface')->reveal();
		$this->container = $this->prophesize('Pimple\Container')->reveal();

		$this->context = new MigrationManagerContext($this->database);
	}

	public function testNoContainerInitially()
	{
		$this->assertNull($this->context->getContainer());
	}

	public function testSetContainer()
	{
		$this->context->setContainer($this->container);

		$this->assertSame($this->container, $this->context->getContainer());
	}

	public function testGetDatabase()
	{
		$this->assertSame($this->database, $this->context->getDatabase());
	}

}
