<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin;


use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\PathCollisionDetector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\PathsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\RegExToken;

class PathsPluginTest extends AbstractPluginTestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $repositoryConnector;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		parent::setupTest();
	}

	public function testGetName()
	{
		$this->assertEquals('paths', $this->plugin->getName());
	}

	public function testGetRevisionQueryFlags()
	{
		$this->assertEquals(
			array(RevisionLog::FLAG_VERBOSE),
			$this->plugin->getRevisionQueryFlags()
		);
	}

	public function testParseIgnoreExisting()
	{
		$this->setLastRevision(500);

		$this->plugin->parse($this->getFixture('svn_log_without_project.xml'));
		$this->assertLastRevision(500);

		$this->assertTablesEmpty(array(
			'Paths', 'Projects', 'ProjectRefs', 'CommitPaths', 'CommitProjects', 'CommitRefs',
		));

		$this->assertStatistics(array());
	}

	public function testParseEmptyCommit()
	{
		$this->plugin->parse($this->getFixture('svn_log_empty_commit.xml'));
		$this->assertLastRevision(100);

		$this->assertTablesEmpty(array(
			'Paths', 'Projects', 'ProjectRefs', 'CommitPaths', 'CommitProjects', 'CommitRefs',
		));

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_EMPTY_COMMIT => 1,
		));
	}

	public function testParseCommitWithoutProject()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_without_project.xml'));
		$this->assertLastRevision(100);

		$this->assertTablesEmpty(array(
			'Projects', 'ProjectRefs', 'CommitProjects', 'CommitRefs',
		));

		$this->assertTableCount('Paths', 1);
		$this->assertTableCount('CommitPaths', 1);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/file.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '2121357014',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 1,
		));
	}

	public function testParseCommitWithProject()
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('#/trunk/#'))->willReturn('trunk')->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_with_project.xml'));
		$this->assertLastRevision(100);

		$this->assertTableCount('Paths', 1);
		$this->assertTableCount('Projects', 1);
		$this->assertTableCount('ProjectRefs', 1);
		$this->assertTableCount('CommitPaths', 1);
		$this->assertTableCount('CommitProjects', 1);
		$this->assertTableCount('CommitRefs', 1);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/trunk/file.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '4102138558',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 1,
			PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
			PathsPlugin::STATISTIC_REF_ADDED => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 1,
		));
	}

	/**
	 * @dataProvider parseProjectPathCollisionDataProvider
	 */
	public function testParseProjectPathCollision($new_db)
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('#/trunk/#'))->willReturn('trunk')->shouldBeCalled();

		if ( !$new_db ) {
			$this->commitBuilder
				->addCommit(100, 'user', 0, 'message')
				->addPath('A', '/path/to/project/trunk/file.txt', 'trunk', '/path/to/project/');

			$this->commitBuilder->build();

			$profiler = $this->database->getProfiler();

			if ( $profiler instanceof StatementProfiler ) {
				$profiler->removeProfile('SELECT Path FROM Projects');
			}

			// Recreate plugin for it to pickup commit added to db above.
			$this->plugin = $this->createPlugin();
		}

		$this->plugin->parse($this->getFixture(
			$new_db ? 'svn_log_project_path_collision1.xml' : 'svn_log_project_path_collision2.xml'
		));
		$this->assertLastRevision(200);

		$this->assertTableCount('Paths', 2);
		$this->assertTableCount('Projects', 1);
		$this->assertTableCount('ProjectRefs', 1);
		$this->assertTableCount('CommitPaths', 2);
		$this->assertTableCount('CommitProjects', 1);
		$this->assertTableCount('CommitRefs', 1);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/trunk/file.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '4102138558',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/project/sub-folder/trunk/file.txt',
					'PathNestingLevel' => '5',
					'PathHash' => '729588925',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
			)
		);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '200',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '2',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
			)
		);

		if ( $new_db ) {
			$this->assertStatistics(array(
				PathsPlugin::STATISTIC_PATH_ADDED => 2,
				PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
				PathsPlugin::STATISTIC_REF_ADDED => 1,
				PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 1,
				PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 1,
				PathsPlugin::STATISTIC_PROJECT_COLLISION_FOUND => 1,
			));
		}
		else {
			$this->assertStatistics(array(
				PathsPlugin::STATISTIC_PATH_ADDED => 1,
				PathsPlugin::STATISTIC_PROJECT_COLLISION_FOUND => 1,
			));
		}
	}

	public static function parseProjectPathCollisionDataProvider()
	{
		return array(
			'new db' => array(true),
			'existing db' => array(false),
		);
	}

	public function testParseDBCacheUsage()
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('#/trunk/#'))->willReturn('trunk')->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_path_reusing.xml'));
		$this->assertLastRevision(200);

		$this->assertTableCount('Paths', 1);
		$this->assertTableCount('Projects', 1);
		$this->assertTableCount('ProjectRefs', 1);
		$this->assertTableCount('CommitPaths', 2);
		$this->assertTableCount('CommitProjects', 2);
		$this->assertTableCount('CommitRefs', 2);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/trunk/file.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '4102138558',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
			)
		);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '200',
					'Action' => 'M',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
				array(
					'Revision' => '200',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
				array(
					'Revision' => '200',
					'RefId' => '1',
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 1,
			PathsPlugin::STATISTIC_PATH_FOUND => 1,
			PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
			PathsPlugin::STATISTIC_PROJECT_FOUND => 1,
			PathsPlugin::STATISTIC_REF_ADDED => 1,
			PathsPlugin::STATISTIC_REF_FOUND => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 2,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 2,
		));
	}

	public function testParsePathCopyExistingSource()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_copy_existing_source.xml'));
		$this->assertLastRevision(200);

		$this->assertTablesEmpty(array(
			'Projects', 'ProjectRefs', 'CommitProjects', 'CommitRefs',
		));

		$this->assertTableCount('Paths', 3);
		$this->assertTableCount('CommitPaths', 3);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/file1.txt',
					'PathNestingLevel' => '3',
					'PathHash' => '2149086466',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/project/file2.txt',
					'PathNestingLevel' => '3',
					'PathHash' => '3350728658',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/project/file3.txt',
					'PathNestingLevel' => '3',
					'PathHash' => '4208469602',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '200',
					'Action' => 'M',
					'Kind' => 'file',
					'PathId' => '2',
					'CopyRevision' => '100',
					'CopyPathId' => '1',
				),
				array(
					'Revision' => '200',
					'Action' => 'M',
					'Kind' => 'file',
					'PathId' => '3',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 3,
			PathsPlugin::STATISTIC_PATH_FOUND => 1,
		));
	}

	public function testParsePathCopyMissingSource()
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('/trunk/'))->willReturn('trunk')->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_copy_missing_source.xml'));
		$this->assertLastRevision(200);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
				array(
					'Revision' => '200',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
				array(
					'Revision' => '200',
					'RefId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/trunk/file1.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '1256725620',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/another-project/trunk/file1.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '2183467823',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/another-project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/project/trunk/file2.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '222848676',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
				array(
					'Id' => '4',
					'Path' => '/path/to/project/trunk/file3.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '807948052',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
			)
		);

		// One less commit path, then paths, because missing copy source paths aren't associated with commits.
		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'file',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '200',
					'Action' => 'M',
					'Kind' => 'file',
					'PathId' => '3',
					'CopyRevision' => '100',
					'CopyPathId' => '2',
				),
				array(
					'Revision' => '200',
					'Action' => 'M',
					'Kind' => 'file',
					'PathId' => '4',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 4,
			PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
			PathsPlugin::STATISTIC_REF_ADDED => 1,
			PathsPlugin::STATISTIC_REF_FOUND => 2,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 2,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 2,
			PathsPlugin::STATISTIC_PROJECT_FOUND => 2,
		));
	}

	public function testParsePathNormalization()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_path_normalization.xml'));
		$this->assertLastRevision(100);

		$this->assertTablesEmpty(array(
			'Projects', 'ProjectRefs', 'CommitProjects', 'CommitRefs',
		));

		$this->assertTableCount('Paths', 1);
		$this->assertTableCount('CommitPaths', 1);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/folder/',
					'PathNestingLevel' => '3',
					'PathHash' => '2118098647',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 1,
		));
	}

	public function testParseProjectPathRetroactiveFillingWithOneRef()
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('#/trunk/#'))->willReturn('trunk')->shouldBeCalled();
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_project_retroactive_filling1.xml'));
		$this->assertLastRevision(100);

		$this->assertTableCount('Paths', 2);
		$this->assertTableCount('Projects', 1);
		$this->assertTableCount('ProjectRefs', 1);
		$this->assertTableCount('CommitPaths', 2);
		$this->assertTableCount('CommitProjects', 1);
		$this->assertTableCount('CommitRefs', 1);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'PathNestingLevel' => '3',
					'PathHash' => '2697522824',
					'RefName' => '',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/project/trunk/',
					'PathNestingLevel' => '4',
					'PathHash' => '2660569320',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '2',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 2,
			PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
			PathsPlugin::STATISTIC_REF_ADDED => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 1,
		));
	}

	public function testParseProjectPathRetroactiveFillingWithTwoRefs()
	{
		$this->repositoryConnector->getRefByPath(new RegExToken('#/trunk/#'))->willReturn('trunk')->shouldBeCalled();
		$this->repositoryConnector->getRefByPath(new RegExToken('#/branches/branch-name/#'))->willReturn('branches/branch-name')->shouldBeCalled();
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_project_retroactive_filling2.xml'));
		$this->assertLastRevision(100);

		$this->assertTableCount('Paths', 4);
		$this->assertTableCount('Projects', 1);
		$this->assertTableCount('ProjectRefs', 2);
		$this->assertTableCount('CommitPaths', 4);
		$this->assertTableCount('CommitProjects', 1);
		$this->assertTableCount('CommitRefs', 2);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'PathNestingLevel' => '3',
					'PathHash' => '2697522824',
					'RefName' => '',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/project/branches/',
					'PathNestingLevel' => '4',
					'PathHash' => '154180554',
					'RefName' => '',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/project/branches/branch-name/',
					'PathNestingLevel' => '5',
					'PathHash' => '1010862080',
					'RefName' => 'branches/branch-name',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '4',
					'Path' => '/path/to/project/trunk/',
					'PathNestingLevel' => '4',
					'PathHash' => '2660569320',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/to/project/',
					'BugRegExp' => null,
					'IsDeleted' => '0',
				),
			)
		);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => '1',
					'ProjectId' => '1',
					'Name' => 'branches/branch-name',
				),
				array(
					'Id' => '2',
					'ProjectId' => '1',
					'Name' => 'trunk',
				),
			)
		);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '1',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '2',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '3',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
				array(
					'Revision' => '100',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => '4',
					'CopyRevision' => null,
					'CopyPathId' => null,
				),
			)
		);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => '1',
				),
			)
		);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => '1',
				),
				array(
					'Revision' => '100',
					'RefId' => '2',
				),
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 4,
			PathsPlugin::STATISTIC_PROJECT_ADDED => 1,
			PathsPlugin::STATISTIC_REF_ADDED => 2,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_PROJECT => 1,
			PathsPlugin::STATISTIC_COMMIT_ADDED_TO_REF => 2,
		));
	}

	/**
	 * @dataProvider parsePathAddedDataProvider
	 */
	public function testParsePathAdded($fixture_file, $revision_added, $revision_last_seen)
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture($fixture_file));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/',
					'PathNestingLevel' => '1',
					'PathHash' => '1753053843',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '50',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/',
					'PathNestingLevel' => '2',
					'PathHash' => '3778857363',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '60',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/file.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '2121357014',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => $revision_added,
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
			)
		);
	}

	public static function parsePathAddedDataProvider()
	{
		return array(
			'path added initially' => array('svn_log_path_added.xml', '100', '100'),
			'path added twice' => array('svn_log_path_added_twice.xml', '100', '200'),
			'path added from past' => array('svn_log_path_added_from_past.xml', '100', '200'),
		);
	}

	/**
	 * @dataProvider parsePathChangedDataProvider
	 */
	public function testParsePathChanged($fixture_file, $revision_last_seen)
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture($fixture_file));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/',
					'PathNestingLevel' => '1',
					'PathHash' => '1753053843',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '50',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/',
					'PathNestingLevel' => '2',
					'PathHash' => '3778857363',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '60',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/file.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '2121357014',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => $revision_last_seen,
				),
			)
		);
	}

	public static function parsePathChangedDataProvider()
	{
		return array(
			'added' => array('svn_log_path_added.xml', '100'),
			'changed' => array('svn_log_path_changed.xml', '200'),
			'replaced' => array('svn_log_path_replaced.xml', '200'),
		);
	}

	public function testParsePathDeletion()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_path_deleted.xml'));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/',
					'PathNestingLevel' => '1',
					'PathHash' => '1753053843',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '50',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/',
					'PathNestingLevel' => '2',
					'PathHash' => '3778857363',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '60',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/file.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '2121357014',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => '200',
					'RevisionLastSeen' => '100',
				),
			)
		);
	}

	public function testParsePathRestoration()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_path_restored.xml'));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/',
					'PathNestingLevel' => '1',
					'PathHash' => '1753053843',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '50',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '300',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/',
					'PathNestingLevel' => '2',
					'PathHash' => '3778857363',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '60',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '300',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/file.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '2121357014',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '300',
				),
			)
		);
	}

	public function testParsePathSorting()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_path_sorting.xml'));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path/',
					'PathNestingLevel' => '1',
					'PathHash' => '1753053843',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path/to/',
					'PathNestingLevel' => '2',
					'PathHash' => '3778857363',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '3',
					'Path' => '/path/to/project/',
					'PathNestingLevel' => '3',
					'PathHash' => '2697522824',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);
	}

	public function testFindNoMatch()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('M', '/path/to/project-a/folder-a/file.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('M', '/path/to/project-b/folder-b/file.php', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEmpty(
			$this->plugin->find(array('/path/to/project-b/folder-b/'), '/path/to/project-a/'),
			'No revisions were found.'
		);
	}

	/**
	 * @dataProvider findFindMissingPathsDataProvider
	 */
	public function testFindMissingPaths($criteria)
	{
		$this->createFixture();

		$this->assertEmpty(
			$this->plugin->find(
				array($criteria),
				'/projects/project/'
			)
		);
	}

	public static function findFindMissingPathsDataProvider()
	{
		return array(
			'guess sub-match' => array('/projects/project/trunk2/'),
			'guess exact' => array('/projects/project/trunk/missing-file.txt'),
			'manual sub-match' => array('sub-match:/projects/project/trunk/missing-file.txt'),
			'manual exact' => array('exact:/projects/project/trunk/missing-file.txt'),
		);
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindNoDuplicatesOnExactMatch()
	{
		$this->createFixture();

		$this->assertEquals(
			array(3, 4),
			$this->plugin->find(
				array(
					'/projects/project/trunk/file1.txt',
					'/projects/project/trunk/file2.txt',
				),
				'/projects/project/'
			)
		);
	}

	public function testFindByExactExistingPath()
	{
		$this->createFixture();

		$this->assertEquals(
			array(2, 20),
			$this->plugin->find(
				array('exact:/projects/project/tags/stable/'),
				'/projects/project/'
			)
		);
	}

	/**
	 * @dataProvider findBySubMatchDataProvider
	 */
	public function testFindNoDuplicatesOnSubMatch($is_ref, $criteria)
	{
		$this->createFixture();

		$this->repositoryConnector->isRefRoot('/projects/project/tags/stable/')->willReturn($is_ref);
		$this->repositoryConnector->isRefRoot('/projects/project/trunk/')->willReturn($is_ref);

		if ( $is_ref ) {
			$this->repositoryConnector->getRefByPath('/projects/project/tags/stable/')->willReturn('tags/stable');
			$this->repositoryConnector->getRefByPath('/projects/project/trunk/')->willReturn('trunk');
		}

		$this->assertEquals(
			array(2, 3, 4, 5, 20, 21),
			$this->plugin->find(array($criteria), '/projects/project/')
		);
	}

	public static function findBySubMatchDataProvider()
	{
		return array(
			'is ref, guess field' => array(true, '/projects/project/tags/stable/'),
			'is not ref, guess field' => array(false, '/projects/project/tags/stable/'),
			'is ref, manual field' => array(true, 'sub-match:/projects/project/tags/stable/'),
			'is not ref, manual field' => array(false, 'sub-match:/projects/project/tags/stable/'),
		);
	}

	public function testFindSorting()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('M', '/path/to/project/folder/file1.php', '', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('M', '/path/to/project/folder/file2.php', '', '/path/to/project/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(
				array(
					'/path/to/project/folder/file2.php',
					'/path/to/project/folder/file1.php',
				),
				'/path/to/project/'
			)
		);
	}

	public function testFindAll()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('M', '/path/to/project-a/folder/file1.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('M', '/path/to/project-a/folder/file2.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(300, 'user', 0, '')
			->addPath('M', '/path/to/project-b/folder/file3.php', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array(''), '/path/to/project-a/')
		);

		// Confirm search is bound to project.
		$this->assertEquals(
			array(300),
			$this->plugin->find(array(''), '/path/to/project-b/')
		);
	}

	public function testFindByAction()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/folder/file.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('M', '/path/to/project-a/folder/file.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(300, 'user', 0, '')
			->addPath('M', '/path/to/project-b/folder/file2.php', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(200),
			$this->plugin->find(array('action:M'), '/path/to/project-a/')
		);

		$this->assertEmpty($this->plugin->find(array('action:D'), '/path/to/project-a/'));
	}

	public function testFindByKind()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project-a/folder/file.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('M', '/path/to/project-a/folder/file.php', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(300, 'user', 0, '')
			->addPath('M', '/path/to/project-b/folder/file2.php', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(array('kind:file'), '/path/to/project-a/')
		);

		$this->assertEmpty($this->plugin->find(array('action:dir'), '/path/to/project-a/'));
	}

	public function testFindUnsupportedField()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('Searching by "field" is not supported by "paths" plugin.');

		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/path/to/project/folder/file.php', '', '/path/to/project/');

		$this->commitBuilder->build();

		$this->plugin->find(array('field:keyword'), '/path/to/project/');
	}

	public function testGetRevisionsDataSuccess()
	{
		$this->createFixture();

		$this->assertEquals(
			array(
				4 => array(
					array(
						'path' => '/projects/project/trunk/file1.txt',
						'kind' => 'file',
						'action' => 'M',
						'copyfrom-path' => null,
						'copyfrom-rev' => null,
					),
					array(
						'path' => '/projects/project/trunk/file2.txt',
						'kind' => 'file',
						'action' => 'R',
						'copyfrom-path' => '/projects/project/trunk/file1.txt',
						'copyfrom-rev' => 3,
					),
				),
			),
			$this->plugin->getRevisionsData(array(4))
		);
	}

	public function testGetRevisionsDataFailure()
	{
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('Revision(-s) "100" not found by "paths" plugin.');

		$this->plugin->getRevisionsData(array(100));
	}

	/**
	 * Creates fixture.
	 *
	 * @return void
	 */
	protected function createFixture()
	{
		$this->commitBuilder
			->addCommit(1, 'user', 0, 'create repo structure')
			->addPath('A', '/projects/', '', '');

		$this->commitBuilder
			->addCommit(2, 'user', 0, 'add project')
			->addPath('A', '/projects/project/', '', '/projects/project/')
			->addPath('A', '/projects/project/trunk/', 'trunk', '/projects/project/')
			->addPath('A', '/projects/project/branches/', '', '/projects/project/')
			->addPath('A', '/projects/project/tags/', '', '/projects/project/');

		$this->commitBuilder
			->addCommit(3, 'user', 0, 'trunk commit 1 (before tags/stable created)')
			->addPath('A', '/projects/project/trunk/file1.txt', 'trunk', '/projects/project/')
			->addPath('A', '/projects/project/trunk/file2.txt', 'trunk', '/projects/project/');

		$this->commitBuilder
			->addCommit(4, 'user', 0, 'trunk commit 2 (before tags/stable created)')
			->addPath('M', '/projects/project/trunk/file1.txt', 'trunk', '/projects/project/')
			->addPath('R', '/projects/project/trunk/file2.txt', 'trunk', '/projects/project/', '/projects/project/trunk/file1.txt', 3);

		$this->commitBuilder
			->addCommit(5, 'user', 0, 'trunk commit 2 (after tags/stable created)')
			->addPath('A', '/projects/project/trunk/file.php', 'trunk', '/projects/project/');

		$this->commitBuilder
			->addCommit(20, 'user', 0, 'copy trunk into stable')
			->addPath('A', '/projects/project/tags/stable/', 'tags/stable', '/projects/project/', '/projects/project/trunk/', 4);

		$this->commitBuilder
			->addCommit(21, 'user', 0, 'tags/stable commit 1')
			->addPath('M', '/projects/project/tags/stable/file1.txt', 'tags/stable', '/projects/project/')
			->addPath('M', '/projects/project/tags/stable/file2.txt', 'tags/stable', '/projects/project/');

		$this->commitBuilder->build();
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new PathsPlugin(
			$this->database,
			$this->filler,
			new DatabaseCache($this->database),
			$this->repositoryConnector->reveal(),
			new PathCollisionDetector()
		);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
