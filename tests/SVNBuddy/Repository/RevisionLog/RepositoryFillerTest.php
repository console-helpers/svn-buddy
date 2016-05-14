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


use ConsoleHelpers\SVNBuddy\Database\DatabaseCache;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RepositoryFiller;

class RepositoryFillerTest extends AbstractDatabaseAwareTestCase
{

	/**
	 * Path collision detector.
	 *
	 * @var RepositoryFiller
	 */
	protected $repositoryFiller;

	protected function setUp()
	{
		parent::setUp();

		$this->repositoryFiller = new RepositoryFiller($this->database, new DatabaseCache($this->database));
	}

	/**
	 * @dataProvider addProjectDataProvider
	 */
	public function testAddProject($is_deleted, $bug_regexp)
	{
		$project_id = $this->repositoryFiller->addProject('/path/', $is_deleted, $bug_regexp);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => $project_id,
					'Path' => '/path/',
					'BugRegExp' => $bug_regexp,
					'IsDeleted' => $is_deleted,
				),
			)
		);

		$this->assertTableEmpty('Paths');
	}

	public function addProjectDataProvider()
	{
		return array(
			array('0', null),
			array('0', ''),
			array('1', ''),
		);
	}

	public function testAddRepositoryWideProject()
	{
		$this->repositoryFiller->addProject('/');

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/',
					'PathNestingLevel' => '0',
					'PathHash' => '2043925204',
					'RefName' => '',
					'ProjectPath' => '/',
					'RevisionAdded' => '0',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '0',
				),
			)
		);
	}

	/**
	 * @dataProvider setProjectStatusDataProvider
	 */
	public function testSetProjectStatus($is_deleted_add, $is_deleted_edit)
	{
		$project_id = $this->repositoryFiller->addProject('/path/', $is_deleted_add);
		$this->repositoryFiller->setProjectStatus($project_id, $is_deleted_edit);

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => $project_id,
					'Path' => '/path/',
					'BugRegExp' => null,
					'IsDeleted' => (string)$is_deleted_edit,
				),
			)
		);
	}

	public function setProjectStatusDataProvider()
	{
		return array(
			array(0, 1),
			array(1, 0),
		);
	}

	public function testSetProjectBugRegexp()
	{
		$project_id = $this->repositoryFiller->addProject('/path/');
		$this->repositoryFiller->setProjectBugRegexp($project_id, 'regexp');

		$this->assertTableContent(
			'Projects',
			array(
				array(
					'Id' => $project_id,
					'Path' => '/path/',
					'BugRegExp' => 'regexp',
					'IsDeleted' => '0',
				),
			)
		);
	}

	public function testAddCommit()
	{
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');

		$this->assertTableContent(
			'Commits',
			array(
				array(
					'Revision' => '100',
					'Author' => 'user',
					'Date' => '123',
					'Message' => 'msg',
				),
			)
		);
	}

	public function testAddCommitToProject()
	{
		$project_id = $this->repositoryFiller->addProject('/path/to/project/');
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$this->repositoryFiller->addCommitToProject(100, $project_id);

		$this->assertTableContent(
			'CommitProjects',
			array(
				array(
					'Revision' => '100',
					'ProjectId' => $project_id,
				),
			)
		);
	}

	public function testAddPath()
	{
		$this->database->setProfiler($this->createStatementProfiler());

		$path1_id = $this->repositoryFiller->addPath(
			'/path/to/project/trunk/file1.txt',
			'trunk',
			'/path/to/project/',
			100
		);

		$path2_id = $this->repositoryFiller->addPath(
			'/path/to/project/trunk/file2.txt',
			'trunk',
			'/path/to/project/',
			100
		);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => $path1_id,
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
					'Id' => $path2_id,
					'Path' => '/path/to/project/trunk/file2.txt',
					'PathNestingLevel' => '4',
					'PathHash' => '222848676',
					'RefName' => 'trunk',
					'ProjectPath' => '/path/to/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);
	}

	public function testAddPathWithPropagation()
	{
		$this->repositoryFiller->addPath('/project/', '', '/project/', 100);
		$this->repositoryFiller->addPath('/project/trunk/', 'trunk', '/project/', 200);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/project/',
					'PathNestingLevel' => '1',
					'PathHash' => '3761055950',
					'RefName' => '',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
				array(
					'Id' => '2',
					'Path' => '/project/trunk/',
					'PathNestingLevel' => '2',
					'PathHash' => '2969229352',
					'RefName' => 'trunk',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '200',
				),
			)
		);
	}

	public function testAddPathToCommit()
	{
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$copy_path_id = $this->repositoryFiller->addPath('/file1.txt', '', '/', 100);

		$this->repositoryFiller->addCommit(200, 'user', 123, 'msg');
		$path_id = $this->repositoryFiller->addPath('/file2.txt', '', '/', 200);

		$this->repositoryFiller->addPathToCommit(200, 'A', 'dir', $path_id, 100, $copy_path_id);

		$this->assertTableContent(
			'CommitPaths',
			array(
				array(
					'Revision' => '200',
					'Action' => 'A',
					'Kind' => 'dir',
					'PathId' => $path_id,
					'CopyRevision' => '100',
					'CopyPathId' => $copy_path_id,
				),
			)
		);
	}

	public function testTouchPath()
	{
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$this->repositoryFiller->addPath('/file1.txt', '', '/', 100);

		$this->repositoryFiller->touchPath('/file1.txt', 200, array('RevisionAdded' => 500));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/file1.txt',
					'PathNestingLevel' => '0',
					'PathHash' => '2532574337',
					'RefName' => '',
					'ProjectPath' => '/',
					'RevisionAdded' => '500',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);
	}

	public function testTouchPathWithPropagation()
	{
		$this->repositoryFiller->addPath('/project/', '', '/project/', 100);
		$this->repositoryFiller->addPath('/project/trunk/', 'trunk', '/project/', 200);
		$this->repositoryFiller->addPath('/project/trunk/file1.txt', 'trunk', '/project/', 300);

		$this->repositoryFiller->touchPath('/project/trunk/file1.txt', 400, array('RevisionAdded' => 500));

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/project/',
					'PathNestingLevel' => '1',
					'PathHash' => '3761055950',
					'RefName' => '',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '400',
				),
				array(
					'Id' => '2',
					'Path' => '/project/trunk/',
					'PathNestingLevel' => '2',
					'PathHash' => '2969229352',
					'RefName' => 'trunk',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '200',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '400',
				),
				array(
					'Id' => '3',
					'Path' => '/project/trunk/file1.txt',
					'PathNestingLevel' => '2',
					'PathHash' => '456399860',
					'RefName' => 'trunk',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '500',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '300',
				),
			)
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "$fields_hash" variable can't be empty.
	 */
	public function testTouchPathWithoutFields()
	{
		$this->repositoryFiller->touchPath('/path/', 100, array());
	}

	/**
	 * @dataProvider getPathTouchFieldsDataProvider
	 */
	public function testGetPathTouchFields($action, $revision, $path_data, $touch_fields)
	{
		$this->assertEquals(
			$touch_fields,
			$this->repositoryFiller->getPathTouchFields($action, $revision, $path_data)
		);
	}

	public function getPathTouchFieldsDataProvider()
	{
		return array(
			'deleted' => array(
				'D',
				100,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 100),
				array('RevisionDeleted' => 100),
			),
			'restored' => array(
				'A',
				300,
				array('RevisionAdded' => 100, 'RevisionDeleted' => 200, 'RevisionLastSeen' => 100),
				array('RevisionDeleted' => null, 'RevisionLastSeen' => 300),
			),
			'added initially' => array(
				'A',
				200,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 100),
				array('RevisionLastSeen' => 200),
			),
			'added twice' => array(
				'A',
				200,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 100),
				array('RevisionLastSeen' => 200),
			),
			'added in past' => array(
				'A',
				100,
				array('RevisionAdded' => 200, 'RevisionDeleted' => null, 'RevisionLastSeen' => 200),
				array('RevisionAdded' => 100),
			),
			'updated' => array(
				'M',
				200,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 100),
				array('RevisionLastSeen' => 200),
			),
			'updated in past' => array(
				'M',
				100,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 200),
				array(),
			),
			'replaced' => array(
				'R',
				200,
				array('RevisionAdded' => 100, 'RevisionDeleted' => null, 'RevisionLastSeen' => 100),
				array('RevisionLastSeen' => 200),
			),
		);
	}

	public function testMovePathsIntoProject()
	{
		$path_id = $this->repositoryFiller->addPath('/path1/', '', '', 100);
		$this->repositoryFiller->addPath('/path2/', '', '', 100);

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path1/',
					'PathNestingLevel' => '1',
					'PathHash' => '3304870543',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path2/',
					'PathNestingLevel' => '1',
					'PathHash' => '4023451980',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);

		$this->repositoryFiller->movePathsIntoProject(array($path_id), '/project/');

		$this->assertTableContent(
			'Paths',
			array(
				array(
					'Id' => '1',
					'Path' => '/path1/',
					'PathNestingLevel' => '1',
					'PathHash' => '3304870543',
					'RefName' => '',
					'ProjectPath' => '/project/',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
				array(
					'Id' => '2',
					'Path' => '/path2/',
					'PathNestingLevel' => '1',
					'PathHash' => '4023451980',
					'RefName' => '',
					'ProjectPath' => '',
					'RevisionAdded' => '100',
					'RevisionDeleted' => null,
					'RevisionLastSeen' => '100',
				),
			)
		);
	}

	public function testAddBugsToCommit()
	{
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$this->repositoryFiller->addBugsToCommit(array('AA', 'BB'), 100);

		$this->assertTableContent(
			'CommitBugs',
			array(
				array(
					'Revision' => '100',
					'Bug' => 'AA',
				),
				array(
					'Revision' => '100',
					'Bug' => 'BB',
				),
			)
		);
	}

	public function testAddMergeCommit()
	{
		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$this->repositoryFiller->addCommit(200, 'user', 123, 'msg');
		$this->repositoryFiller->addCommit(300, 'user', 123, 'msg');
		$this->repositoryFiller->addMergeCommit(300, array(100, 200));

		$this->assertTableContent(
			'Merges',
			array(
				array(
					'MergeRevision' => '300',
					'MergedRevision' => '100',
				),
				array(
					'MergeRevision' => '300',
					'MergedRevision' => '200',
				),
			)
		);
	}

	public function testAddRefToProject()
	{
		$project_id = $this->repositoryFiller->addProject('/project/');
		$ref_id = $this->repositoryFiller->addRefToProject('trunk', $project_id);

		$this->assertTableContent(
			'ProjectRefs',
			array(
				array(
					'Id' => $ref_id,
					'ProjectId' => $project_id,
					'Name' => 'trunk',
				),
			)
		);
	}

	public function testAddCommitToRef()
	{
		$project_id = $this->repositoryFiller->addProject('/project/');
		$ref_id = $this->repositoryFiller->addRefToProject('trunk', $project_id);

		$this->repositoryFiller->addCommit(100, 'user', 123, 'msg');
		$this->repositoryFiller->addCommitToRef(100, $ref_id);

		$this->assertTableContent(
			'CommitRefs',
			array(
				array(
					'Revision' => '100',
					'RefId' => $ref_id,
				),
			)
		);
	}

}
