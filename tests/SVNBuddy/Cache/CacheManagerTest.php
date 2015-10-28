<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Cache;


use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use Tests\ConsoleHelpers\SVNBuddy\WorkingDirectoryAwareTestCase;

class CacheManagerTest extends WorkingDirectoryAwareTestCase
{

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	protected $cacheManager;

	protected function setUp()
	{
		parent::setUp();

		$this->cacheManager = new CacheManager($this->getWorkingDirectory());
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The $name parameter must be in "namespace:name" format.
	 */
	public function testCacheNameWithoutNamespaceError()
	{
		$this->cacheManager->setCache('name', 'value');
	}

	public function testCacheFileNamingPattern()
	{
		$this->assertCount(0, $this->getCacheFilenames('namespace'));

		$this->cacheManager->setCache('namespace:name', 'value');

		$this->assertCount(1, $this->getCacheFilenames('namespace'));
	}

	public function testSetWithoutDuration()
	{
		$this->cacheManager->setCache('namespace:name', 'value');
		$this->assertEquals('value', $this->cacheManager->getCache('namespace:name'));
	}

	/**
	 * @medium
	 */
	public function testSetWithDuration()
	{
		$this->cacheManager->setCache('namespace:name_int', 'value_int', null, 1);
		$this->assertEquals('value_int', $this->cacheManager->getCache('namespace:name_int'));

		$this->cacheManager->setCache('namespace:name_string', 'value_string', null, '1 second');
		$this->assertEquals('value_string', $this->cacheManager->getCache('namespace:name_string'));

		sleep(2);

		$this->assertNull($this->cacheManager->getCache('namespace:name_int'));
		$this->assertNull($this->cacheManager->getCache('namespace:name_string'));
	}

	public function testSetWithInvalidatorSuccess()
	{
		$this->cacheManager->setCache('namespace:name', 'value', 'invalidator1');
		$this->assertEquals('value', $this->cacheManager->getCache('namespace:name', 'invalidator1'));
		$this->assertCount(1, $this->getCacheFilenames('namespace'));
	}

	public function testSetWithInvalidatorFailure()
	{
		$this->cacheManager->setCache('namespace:name', 'value', 'invalidator1');
		$this->assertNull($this->cacheManager->getCache('namespace:name', 'invalidator2'));
		$this->assertCount(0, $this->getCacheFilenames('namespace'));
	}

	/**
	 * Returns cache filenames from given namespace.
	 *
	 * @param string $namespace Namespace.
	 *
	 * @return array
	 */
	protected function getCacheFilenames($namespace)
	{
		return glob($this->getWorkingDirectory() . '/' . $namespace . '_*.cache');
	}

}
