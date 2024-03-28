<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\PathCollisionDetector;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class PathCollisionDetectorTest extends AbstractTestCase
{

	/**
	 * Path collision detector.
	 *
	 * @var PathCollisionDetector
	 */
	protected $pathCollisionDetector;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->pathCollisionDetector = new PathCollisionDetector();
	}

	public function testNoCollisionWithoutPaths()
	{
		$this->assertFalse($this->pathCollisionDetector->isCollision('/path/to/folder/'));
	}

	public function testNoCollisionForKnownPath()
	{
		$this->pathCollisionDetector->addPaths(array('/path/to/folder/'));

		$this->assertFalse($this->pathCollisionDetector->isCollision('/path/to/folder/'));
	}

	public function testNoCollisionWithDifferentParentPath()
	{
		$this->pathCollisionDetector->addPaths(array('/path/to/folder/'));

		$this->assertFalse($this->pathCollisionDetector->isCollision('/another-path/to/folder/'));
	}

	public function testNoCollisionForRootPathWithoutKnownPaths()
	{
		$this->assertFalse($this->pathCollisionDetector->isCollision('/'));
	}

	public function testCollisionForRootPathWithKnownPaths()
	{
		$this->pathCollisionDetector->addPaths(array('/path/to/project/'));

		$this->assertTrue($this->pathCollisionDetector->isCollision('/'));
	}

	public function testCollisionOnParentPath()
	{
		$this->pathCollisionDetector->addPaths(array('/path/to/folder/'));

		$this->assertTrue($this->pathCollisionDetector->isCollision('/path/to/'));
	}

	public function testCollisionOnChildPath()
	{
		$this->pathCollisionDetector->addPaths(array('/path/to/folder/'));

		$this->assertTrue($this->pathCollisionDetector->isCollision('/path/to/folder/sub-folder/'));
	}

	public function testCollisionWithRootPathIsKnownPath()
	{
		$this->pathCollisionDetector->addPaths(array('/'));

		$this->assertTrue($this->pathCollisionDetector->isCollision('/path/to/folder/'));
	}

	public function testNoCollisionWithRootPathIsKnownPath()
	{
		$this->pathCollisionDetector->addPaths(array('/'));

		$this->assertFalse($this->pathCollisionDetector->isCollision('/'));
	}

}
