<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\ConsoleKit\Helper;


use ConsoleHelpers\ConsoleKit\Helper\ContainerHelper;
use ConsoleHelpers\ConsoleKit\Container;

class ContainerHelperTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Container helper
	 *
	 * @var ContainerHelper
	 */
	protected $containerHelper;

	/**
	 * Container.
	 *
	 * @var Container
	 */
	protected $container;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->prophesize('Pimple\\Container')->reveal();
		$this->containerHelper = new ContainerHelper($this->container);
	}

	public function testGetContainer()
	{
		$this->assertSame($this->container, $this->containerHelper->getContainer());
	}

	public function testGetName()
	{
		$this->assertEquals('container', $this->containerHelper->getName());
	}

}
