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

	protected function setUp()
	{
		$this->repositoryConnector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');

		parent::setUp();
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

			/** @var StatementProfiler $profiler */
			$profiler = $this->database->getProfiler();
			$profiler->removeProfile('SELECT Path FROM Projects');

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

	public function parseProjectPathCollisionDataProvider()
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

	public function testParsePathCopyOperation()
	{
		$this->repositoryConnector->getRefByPath(Argument::any())->willReturn(false)->shouldBeCalled();

		$this->plugin->parse($this->getFixture('svn_log_copy_operation.xml'));
		$this->assertLastRevision(200);

		$this->assertTablesEmpty(array(
			'Projects', 'ProjectRefs', 'CommitProjects', 'CommitRefs',
		));

		$this->assertTableCount('Paths', 2);
		$this->assertTableCount('CommitPaths', 2);

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
			)
		);

		$this->assertStatistics(array(
			PathsPlugin::STATISTIC_PATH_ADDED => 2,
			PathsPlugin::STATISTIC_PATH_FOUND => 1,
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

	public function parsePathAddedDataProvider()
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

	public function parsePathChangedDataProvider()
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

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindNoDuplicatesOnExactMatch()
	{
		$this->createFixture();

		$this->assertEquals(
			array(100),
			$this->plugin->find(
				array(
					'/path/to/project/folder/file1.php',
					'/path/to/project/folder/file2.php',
				),
				'/path/to/project/'
			)
		);
	}

	public function testFindNoDuplicatesOnSubMatch()
	{
		$this->createFixture();

		$this->assertEquals(
			array(100),
			$this->plugin->find(array('/path/to/project/folder/'), '/path/to/project/')
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

	public function testGetRevisionsDataSuccess()
	{
		$this->createFixture();

		$this->assertEquals(
			array(
				100 => array(
					array(
						'path' => '/path/to/project/folder/file1.php',
						'kind' => 'file',
						'action' => 'M',
						'copyfrom-path' => null,
						'copyfrom-rev' => null,
					),
					array(
						'path' => '/path/to/project/folder/file2.php',
						'kind' => 'file',
						'action' => 'M',
						'copyfrom-path' => '/path/to/file.php',
						'copyfrom-rev' => 50,
					),
				),
			),
			$this->plugin->getRevisionsData(array(100))
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Revision(-s) "100" not found by "paths" plugin.
	 */
	public function testGetRevisionsDataFailure()
	{
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
			->addCommit(100, 'user', 0, '')
			->addPath('M', '/path/to/project/folder/file1.php', '', '/path/to/project/')
			->addPath('M', '/path/to/project/folder/file2.php', '', '/path/to/project/', '/path/to/file.php', 50);

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
