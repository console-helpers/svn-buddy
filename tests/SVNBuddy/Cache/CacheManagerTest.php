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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\ConsoleKit\WorkingDirectoryAwareTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class CacheManagerTest extends WorkingDirectoryAwareTestCase
{

	use ExpectException;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	protected $cacheManager;

	/**
	 * Size helper.
	 *
	 * @var ObjectProphecy
	 */
	protected $sizeHelper;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		parent::setupTest();

		$this->sizeHelper = $this->prophesize('ConsoleHelpers\SVNBuddy\Helper\SizeHelper');
		$this->cacheManager = new CacheManager($this->getWorkingDirectory(), $this->sizeHelper->reveal());
	}

	public function testCacheNameWithoutNamespaceError()
	{
		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage('The $name parameter must be in "namespace:name" format.');

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
		$this->assertEquals('value_int', $this->cacheManager->getCache('namespace:name_int', null, 1));

		$this->cacheManager->setCache('namespace:name_string', 'value_string', null, '1 second');
		$this->assertEquals('value_string', $this->cacheManager->getCache('namespace:name_string', null, '1 second'));

		sleep(2);

		$this->assertNull($this->cacheManager->getCache('namespace:name_int', null, 1));
		$this->assertNull($this->cacheManager->getCache('namespace:name_string', null, '1 second'));
	}

	public function testSetWithSameNameButDifferentDurations()
	{
		$this->cacheManager->setCache('namespace:name', 'shorter_life', null, '5 minute');
		$this->cacheManager->setCache('namespace:name', 'longer_life', null, '10 minute');
		$this->cacheManager->setCache('namespace:name', 'unlimited_life');

		$this->assertEquals('shorter_life', $this->cacheManager->getCache('namespace:name', null, '5 minute'));
		$this->assertEquals('longer_life', $this->cacheManager->getCache('namespace:name', null, '10 minute'));
		$this->assertEquals('unlimited_life', $this->cacheManager->getCache('namespace:name'));
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

	public function testGetWithSubFolder()
	{
		$this->assertNull($this->cacheManager->getCache('sub-folder/namespace:name'));
	}

	public function testSetWithSubFolder()
	{
		$this->cacheManager->setCache('sub-folder/namespace:name', 'OK');
		$this->assertEquals('OK', $this->cacheManager->getCache('sub-folder/namespace:name'));
	}

	public function testNonVerboseIO()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(false)->shouldBeCalled();

		$cache_manager = new CacheManager($this->getWorkingDirectory(), $this->sizeHelper->reveal(), $io->reveal());
		$this->assertNull($cache_manager->getCache('namespace:name'));
	}

	public function testVerboseCacheMiss()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(true)->shouldBeCalled();

		// For "getCache" call.
		$this->expectVerboseOutput($io, '#^<debug>\[cache\]: .*/\.svn-buddy/namespace_.*\.cache \(miss\)</debug>$#');

		$cache_manager = new CacheManager($this->getWorkingDirectory(), $this->sizeHelper->reveal(), $io->reveal());
		$this->assertNull($cache_manager->getCache('namespace:name'));
	}

	public function testVerboseCacheHit()
	{
		$io = $this->prophesize('ConsoleHelpers\ConsoleKit\ConsoleIO');
		$io->isVerbose()->willReturn(true)->shouldBeCalled();

		// For "setCache" call.
		$this->expectVerboseOutput($io, '#^<debug>\[cache\]: .*/\.svn-buddy/namespace_.*\.cache \(miss\)</debug>$#');

		// For "getCache" call.
		$this->expectVerboseOutput($io, '#^<debug>\[cache\]: .*/\.svn-buddy/namespace_.*\.cache \(hit: .*\)</debug>$#');

		$cache_manager = new CacheManager($this->getWorkingDirectory(), $this->sizeHelper->reveal(), $io->reveal());
		$cache_manager->setCache('namespace:name', 'OK');
		$this->assertEquals('OK', $cache_manager->getCache('namespace:name'));
	}

	/**
	 * @dataProvider removalOfExistingCacheDataProvider
	 */
	public function testRemovalOfExistingCache($duration)
	{
		$this->assertCount(0, $this->getCacheFilenames('namespace'));

		$this->cacheManager->setCache('namespace:name', 'value', $duration);

		$this->assertCount(1, $this->getCacheFilenames('namespace'));

		$this->cacheManager->deleteCache('namespace:name');

		$this->assertCount(0, $this->getCacheFilenames('namespace'));
	}

	public function removalOfExistingCacheDataProvider()
	{
		return array(
			'without duration' => array(null),
			'with duration' => array(3600),
		);
	}

	public function testRemovalOfNonExistingCache()
	{
		$this->assertCount(0, $this->getCacheFilenames('namespace'));

		$this->cacheManager->deleteCache('namespace:name');

		$this->assertCount(0, $this->getCacheFilenames('namespace'));
	}

	/**
	 * Expects verbose output.
	 *
	 * @param ObjectProphecy $io     ConsoleIO mock.
	 * @param string         $regexp Regexp.
	 *
	 * @return void
	 */
	protected function expectVerboseOutput(ObjectProphecy $io, $regexp)
	{
		$io->writeln(Argument::that(function (array $messages) use ($regexp) {
			return count($messages) === 2 && $messages[0] === '' && preg_match($regexp, $messages[1]);
		}))->shouldBeCalled();
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
