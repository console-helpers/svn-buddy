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


use ConsoleHelpers\SVNBuddy\Cache\FileCacheStorage;
use PHPUnit\Framework\TestCase;

class FileCacheStorageTest extends TestCase
{

	/**
	 * Cache.
	 *
	 * @var FileCacheStorage
	 */
	protected $cache;

	/**
	 * Cache file.
	 *
	 * @var string.
	 */
	protected $cacheFile;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->cacheFile = sys_get_temp_dir() . '/test.cache';

		$this->cache = new FileCacheStorage($this->cacheFile);
	}

	public function testNoCacheFileByDefault()
	{
		$this->assertFileNotExists($this->cacheFile);
	}

	public function testMissingCacheFile()
	{
		$this->assertNull($this->cache->get());
	}

	public function testRegularUsage()
	{
		$expected = array('key' => 'value');
		$this->cache->set($expected);

		$this->assertFileExists($this->cacheFile);
		$this->assertSame($expected, $this->cache->get());
		$this->assertEquals(json_encode($expected), file_get_contents($this->cacheFile));
	}

	public function testCompressedUsage()
	{
		$expected = array('key' => str_repeat('value', 2048));
		$this->cache->set($expected);

		$this->assertFileExists($this->cacheFile);
		$this->assertSame($expected, $this->cache->get());
		$this->assertNotEquals(json_encode($expected), file_get_contents($this->cacheFile));
	}

	public function testEmptyCache()
	{
		$this->cache->set(array());

		$this->assertNull($this->cache->get());
	}

	public function testNothingToInvalidate()
	{
		$this->cache->invalidate();
		$this->assertFileNotExists($this->cacheFile);
	}

	public function testSomethingToInvalidate()
	{
		$this->cache->set(array('key' => 'value'));
		$this->cache->invalidate();
		$this->assertFileNotExists($this->cacheFile);
	}

	/**
	 * Removes temporary files.
	 *
	 * @after
	 * @return void
	 */
	protected function teardownTest()
	{
		if ( file_exists($this->cacheFile) ) {
			unlink($this->cacheFile);
		}
	}

}
