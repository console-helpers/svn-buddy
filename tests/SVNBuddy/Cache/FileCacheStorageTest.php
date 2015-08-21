<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Cache;


use aik099\SVNBuddy\Cache\FileCacheStorage;
use Mockery as m;

class FileCacheStorageTest extends \PHPUnit_Framework_TestCase
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

	protected function setUp()
	{
		parent::setUp();

		$this->cacheFile = sys_get_temp_dir() . '/repository.cache';
		$this->cache = new FileCacheStorage($this->cacheFile);
	}

	public function testNoCacheFileByDefault()
	{
		$this->assertFileNotExists($this->cacheFile);
	}

	public function testEmptyCacheOnMissingCacheFile()
	{
		$this->assertCount(0, $this->cache->get());
	}

	public function testEmptyCacheFile()
	{
		file_put_contents($this->cacheFile, '');
		$this->assertCount(0, $this->cache->get());
	}

	public function testGetCache()
	{
		$expected = array('key1' => 'val1', 'key2' => 'val2');

		$this->cache->set($expected);
		$this->assertEquals($expected, $this->cache->get());
	}

	/**
	 * Removes temporary files.
	 *
	 * @return void
	 */
	protected function tearDown()
	{
		if ( file_exists($this->cacheFile) ) {
			unlink($this->cacheFile);
		}
	}

}
