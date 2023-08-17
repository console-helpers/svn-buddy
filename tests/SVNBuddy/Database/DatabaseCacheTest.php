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
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class DatabaseCacheTest extends TestCase
{

	/**
	 * Database.
	 *
	 * @var ObjectProphecy
	 */
	protected $database;

	/**
	 * Database cache.
	 *
	 * @var DatabaseCache
	 */
	protected $databaseCache;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->database = $this->prophesize('Aura\Sql\ExtendedPdoInterface');
		$this->databaseCache = new DatabaseCache($this->database->reveal());
	}

	public function testSet()
	{
		$this->databaseCache->cacheTable('Tests');
		$this->assertFalse(
			$this->databaseCache->getFromCache('Tests', 'key'),
			'For missing cache keys the "false" is returned.'
		);

		$this->databaseCache->setIntoCache('Tests', 'key', array('aa' => 'bb', 'cc' => 'dd'));
		$this->assertEquals(
			array('aa' => 'bb', 'cc' => 'dd'),
			$this->databaseCache->getFromCache('Tests', 'key'),
			'The retrieved value matches one, that was set.'
		);

		$this->databaseCache->setIntoCache('Tests', 'key', array('aa' => 'bb2', 'ee' => 'ff'));
		$this->assertEquals(
			array('aa' => 'bb2', 'cc' => 'dd', 'ee' => 'ff'),
			$this->databaseCache->getFromCache('Tests', 'key'),
			'When value already exists, then new value is merged with existing one'
		);
	}

	public function testFallbackQueryIsExecutedOnMissingCacheNotFound()
	{
		$this->database->fetchOne('sql', array('param' => 'value'))->willReturn(false)->shouldBeCalled();

		$this->databaseCache->cacheTable('Tests');

		$this->assertFalse(
			$this->databaseCache->getFromCache('Tests', 'key', 'sql', array('param' => 'value')),
			'Attempt to populate cache by running SQL, that returns nothing does nothing.'
		);
		$this->assertFalse(
			$this->databaseCache->getFromCache('Tests', 'key'),
			'For missing cache keys the "false" is returned.'
		);
	}

	public function testFallbackQueryIsExecutedOnMissingCacheFound()
	{
		$sql_result = array('aa' => 'bb', 'cc' => 'dd');

		$this->database
			->fetchOne('sql', array('param' => 'value'))
			->willReturn($sql_result)
			->shouldBeCalled();

		$this->databaseCache->cacheTable('Tests');

		$this->assertEquals(
			$sql_result,
			$this->databaseCache->getFromCache('Tests', 'key', 'sql', array('param' => 'value')),
			'Attempt to populate cache by running SQL, that returns something is stored to cache.'
		);
		$this->assertEquals(
			$sql_result,
			$this->databaseCache->getFromCache('Tests', 'key'),
			'Fallback SQL result was stored into cache.'
		);
	}

	public function testReinitializationWontDropExistingCache()
	{
		$this->databaseCache->cacheTable('Tests');
		$this->databaseCache->setIntoCache('Tests', 'key', array('param' => 'value'));

		$this->databaseCache->cacheTable('Tests');
		$this->assertEquals(array('param' => 'value'), $this->databaseCache->getFromCache('Tests', 'key'));
	}

	public function testClear()
	{
		$this->databaseCache->cacheTable('Tests');

		$this->databaseCache->setIntoCache('Tests', 'key', array('aa' => 'bb'));
		$this->assertEquals(array('aa' => 'bb'), $this->databaseCache->getFromCache('Tests', 'key'));

		$this->databaseCache->clear();

		$this->assertFalse(
			$this->databaseCache->getFromCache('Tests', 'key'),
			'All values were removed from cache.'
		);
	}

}
