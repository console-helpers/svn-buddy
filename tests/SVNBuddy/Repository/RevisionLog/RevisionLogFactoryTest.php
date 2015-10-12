<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Repository\RevisionLog;


use aik099\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;

class RevisionLogFactoryTest extends \PHPUnit_Framework_TestCase
{

	public function testGetRevisionLog()
	{
		$repository_connector = $this->prophesize('aik099\\SVNBuddy\\Repository\\Connector\\Connector');

		$repository_connector->withCache('1 year')->willReturn($repository_connector)->shouldBeCalled();
		$repository_connector->getProperty('bugtraq:logregex', 'svn://localhost/trunk')->willReturn('')->shouldBeCalled();

		$repository_connector->getFirstRevision('svn://localhost')->willReturn(1)->shouldBeCalled();
		$repository_connector->getLastRevision('svn://localhost')->willReturn(1)->shouldBeCalled();

		$cache_manager = $this->prophesize('aik099\\SVNBuddy\\Cache\\CacheManager');
		$cache_manager->getCache('log:svn://localhost')->shouldBeCalled();

		$io = $this->prophesize('aik099\\SVNBuddy\\ConsoleIO');

		$factory = new RevisionLogFactory($repository_connector->reveal(), $cache_manager->reveal(), $io->reveal());
		$revision_log = $factory->getRevisionLog('svn://localhost/trunk');

		$this->assertInstanceOf('aik099\\SVNBuddy\\Repository\\RevisionLog\\RevisionLog', $revision_log);
	}

}