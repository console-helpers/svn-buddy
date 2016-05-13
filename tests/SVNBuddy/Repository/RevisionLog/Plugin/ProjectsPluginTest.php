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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\ProjectsPlugin;

class ProjectsPluginTest extends AbstractPluginTestCase
{

	public function testGetName()
	{
		$this->assertEquals('projects', $this->plugin->getName());
	}

	public function testFindNonExistingProject()
	{
		$this->markTestSkipped('The "' . $this->plugin->getName() . '" doesn\'t check given project existence.');
	}

	public function testProcessProjectRemoved()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'project added')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'project removed')
			->addPath('D', '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder->build();

		$this->plugin->process(0, 100);

		$sql = 'SELECT IsDeleted
				FROM Projects
				WHERE Id = :id';
		$is_deleted = $this->database->fetchValue($sql, array(
			'id' => $this->getProjectId('/path/to/project/'),
		));

		$this->assertEquals(1, $is_deleted, 'Project was marked as deleted');

		$this->assertStatistics(array(
			ProjectsPlugin::STATISTIC_PROJECT_DELETED => 1,
		));
	}

	/**
	 * @dataProvider processProjectRestoredDataProvider
	 */
	public function testProcessProjectRestored($action)
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'project added')
			->addPath('A', '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'project removed')
			->addPath('D', '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder
			->addCommit(300, 'user', 0, 'project added')
			->addPath($action, '/path/to/project/', '', '/path/to/project/');

		$this->commitBuilder->build();

		$project_id = $this->getProjectId('/path/to/project/');

		$this->filler->setProjectStatus($project_id, 1);

		$this->plugin->process(0, 100);

		$sql = 'SELECT IsDeleted
				FROM Projects
				WHERE Id = :id';
		$is_deleted = $this->database->fetchValue($sql, array('id' => $project_id));

		$this->assertEquals(0, $is_deleted, 'Project was marked as deleted');

		$this->assertStatistics(array(
			ProjectsPlugin::STATISTIC_PROJECT_RESTORED => 1,
		));
	}

	public function processProjectRestoredDataProvider()
	{
		return array(
			'added' => array('A'),
			'replaced' => array('R'),
		);
	}

	public function testProcessLastRevisionUpdated()
	{
		$this->plugin->process(0, 100);
		$this->assertLastRevision(100);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The "/path/to/project-a/" project(-s) not found by "projects" plugin.
	 *
	 * @return void
	 */
	public function testFindUnknownProject()
	{
		$this->plugin->find(array('/path/to/project-a/'), '');
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), ''), 'No revisions were found.');
	}

	public function testFindNoDuplicates()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'project added')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addPath('A', '/path/to/project-b/', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100),
			$this->plugin->find(
				array(
					'/path/to/project-a/',
					'/path/to/project-b/',
				),
				''
			)
		);
	}

	public function testFindSorting()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'project added')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, 'project added')
			->addPath('A', '/path/to/project-b/', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(100, 200),
			$this->plugin->find(
				array(
					'/path/to/project-b/',
					'/path/to/project-a/',
				),
				''
			)
		);
	}

	public function testGetRevisionsData()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, 'project added')
			->addPath('A', '/path/to/project-a/', '', '/path/to/project-a/')
			->addPath('A', '/path/to/project-b/', '', '/path/to/project-b/');

		$this->commitBuilder->build();

		$this->assertEquals(
			array(
				100 => array(
					'/path/to/project-a/',
					'/path/to/project-b/',
				),
				105 => array(),
			),
			$this->plugin->getRevisionsData(array(100, 105))
		);
	}

	/**
	 * Returns project id.
	 *
	 * @param string $project_path Project path.
	 *
	 * @return integer
	 */
	protected function getProjectId($project_path)
	{
		$sql = 'SELECT Id
				FROM Projects
				WHERE Path = :path';

		return $this->database->fetchValue($sql, array('path' => $project_path));
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new ProjectsPlugin($this->database, $this->filler);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
