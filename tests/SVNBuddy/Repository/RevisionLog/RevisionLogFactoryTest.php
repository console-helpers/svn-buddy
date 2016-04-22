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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use Prophecy\Argument;

class RevisionLogFactoryTest extends \PHPUnit_Framework_TestCase
{

	public function testGetRevisionLog()
	{
		$repository_connector = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector');

		$repository_connector->withCache('1 year')->willReturn($repository_connector)->shouldBeCalled();
		$repository_connector->getProperty('bugtraq:logregex', 'svn://localhost/trunk')->willReturn('')->shouldBeCalled();

		$repository_connector->getFirstRevision('svn://localhost')->willReturn(1)->shouldBeCalled();
		$repository_connector->getLastRevision('svn://localhost')->willReturn(1)->shouldBeCalled();

		$repository_connector->getProjectUrl('svn://localhost/trunk')->willReturn('svn://localhost')->shouldBeCalled();

		$cache_manager = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager');
		$cache_manager->getCache('localhost/log:svn://localhost', Argument::containingString('main:'))->shouldBeCalled();

		$io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');

		$factory = new RevisionLogFactory($repository_connector->reveal(), $cache_manager->reveal());
		$revision_log = $factory->getRevisionLog('svn://localhost/trunk', $io->reveal());

		$this->assertInstanceOf('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\RevisionLog', $revision_log);
	}

}
