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

class RevisionLogFactoryTest extends AbstractDatabaseAwareTestCase
{

	public function testGetRevisionLog()
	{
		$repository_connector = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Repository\\Connector\\Connector');

		$repository_connector->getLastRevision('svn://localhost')->willReturn(0)->shouldBeCalled();

		$repository_connector->getRootUrl('svn://localhost/projects/project-name/trunk')->willReturn('svn://localhost')->shouldBeCalled();
		$repository_connector->getRelativePath('svn://localhost/projects/project-name/trunk')->willReturn('/projects/project-name/trunk')->shouldBeCalled();
		$repository_connector->getProjectUrl('/projects/project-name/trunk')->willReturn('/projects/project-name')->shouldBeCalled();
		$repository_connector->getRefByPath('/projects/project-name/trunk')->willReturn('trunk')->shouldBeCalled();

		$database_manager = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\RevisionLog\DatabaseManager');
		$database_manager->getDatabase('svn://localhost', null)->willReturn($this->database);
		$database_manager->runMigrations(Argument::type('ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\MigrationContext'))->shouldBeCalled();

		$log_message_parser_factory = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParserFactory');

		$factory = new RevisionLogFactory($repository_connector->reveal(), $database_manager->reveal(), $log_message_parser_factory->reveal());
		$this->assertInstanceOf(
			'ConsoleHelpers\\SVNBuddy\\Repository\\RevisionLog\\RevisionLog',
			$factory->getRevisionLog('svn://localhost/projects/project-name/trunk')
		);
	}

}
