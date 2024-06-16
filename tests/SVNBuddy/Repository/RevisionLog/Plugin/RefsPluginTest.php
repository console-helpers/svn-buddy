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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\DatabaseCollectorPlugin\RefsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\IPlugin;
use Prophecy\Prophecy\ObjectProphecy;

class RefsPluginTest extends AbstractPluginTestCase
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
		$this->assertEquals('refs', $this->plugin->getName());
	}

	public function testProcess()
	{
		$this->plugin->process(0, 100);
		$this->assertLastRevision(100);
	}

	public function testFindAll()
	{
		$this->commitBuilder
			->addCommit(100, 'user', 0, '')
			->addPath('A', '/project/branches/branch-name/', 'branches/branch-name', '/project/')
			->addPath('A', '/project/tags/tag-name/', 'tags/tag-name', '/project/');

		$this->commitBuilder->build();

		$revision_log = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog');
		$revision_log->find('paths', '/project/')->willReturn(array(1, 2, 3))->shouldBeCalled();
		$this->plugin->setRevisionLog($revision_log->reveal());


		$this->assertEquals(
			array(1, 2, 3),
			$this->plugin->find(array('all'), '/project/')
		);
	}

	public function testFindMultipleRefs()
	{
		$this->commitBuilder
			->addCommit(1, 'user', 0, '')
			->addPath('A', '/project/branches/branch-name/', 'branches/branch-name', '/project/')
			->addPath('A', '/project/tags/tag-name/', 'tags/tag-name', '/project/');

		$this->commitBuilder
			->addCommit(2, 'user', 0, '')
			->addPath('A', '/project/branches/branch-name/file.txt', 'branches/branch-name', '/project/');

		$this->commitBuilder
			->addCommit(3, 'user', 0, '')
			->addPath('A', '/project/tags/tag-name/file.txt', 'tags/tag-name', '/project/');

		$this->commitBuilder
			->addCommit(4, 'user', 0, '')
			->addPath('A', '/project/tags/another-tag-name/', 'tags/tag-name', '/project/');

		$this->commitBuilder->build();

		$revision_log = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog');
		$revision_log->find('paths', '/project/branches/branch-name/')->willReturn(array(1, 2))->shouldBeCalled();
		$revision_log->find('paths', '/project/tags/tag-name/')->willReturn(array(2, 3))->shouldBeCalled();
		$this->plugin->setRevisionLog($revision_log->reveal());

		$this->assertEquals(
			array(1, 2, 3),
			$this->plugin->find(array('branches/branch-name', 'tags/tag-name'), '/project/')
		);
	}

	public function testFindSingleRefs()
	{
		$this->commitBuilder
			->addCommit(1, 'user', 0, '')
			->addPath('A', '/project/branches/branch-name/', 'branches/branch-name', '/project/')
			->addPath('A', '/project/tags/tag-name/', 'tags/tag-name', '/project/');

		$this->commitBuilder
			->addCommit(2, 'user', 0, '')
			->addPath('A', '/project/branches/branch-name/file.txt', 'branches/branch-name', '/project/');

		$this->commitBuilder
			->addCommit(3, 'user', 0, '')
			->addPath('A', '/project/tags/tag-name/file.txt', 'tags/tag-name', '/project/');

		$this->commitBuilder
			->addCommit(4, 'user', 0, '')
			->addPath('A', '/project/tags/another-tag-name/', 'tags/tag-name', '/project/');

		$this->commitBuilder->build();

		$revision_log = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog');
		$revision_log->find('paths', '/project/branches/branch-name/')->willReturn(array(1, 2))->shouldBeCalled();
		$this->plugin->setRevisionLog($revision_log->reveal());

		$this->assertEquals(
			array(1, 2),
			$this->plugin->find(array('branches/branch-name'), '/project/')
		);
	}

	public function testFindWithEmptyCriteria()
	{
		$this->assertEmpty($this->plugin->find(array(), '/path/to/project/'), 'No revisions were found.');
	}

	public function testFindAllRefs()
	{
		$this->createFixture();

		$this->assertEquals(
			array('branches/branch-name', 'tags/tag-name'),
			$this->plugin->find(array('all_refs'), '/path/to/project-a/')
		);
	}

	public function testGetRevisionsData()
	{
		$this->createFixture();

		$this->assertEquals(
			array(
				100 => array('branches/branch-name', 'tags/tag-name'),
				105 => array(),
			),
			$this->plugin->getRevisionsData(array(100, 105))
		);
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
			->addPath('A', '/path/to/project-a/branches/branch-name/', 'branches/branch-name', '/path/to/project-a/')
			->addPath('A', '/path/to/project-a/tags/tag-name/', 'tags/tag-name', '/path/to/project-a/');

		$this->commitBuilder
			->addCommit(200, 'user', 0, '')
			->addPath('A', '/path/to/project-b/branches/branch-name/', 'branches/branch-name', '/path/to/project-b/');

		$this->commitBuilder->build();
	}

	/**
	 * Creates plugin.
	 *
	 * @return IPlugin
	 */
	protected function createPlugin()
	{
		$plugin = new RefsPlugin($this->database, $this->filler);
		$plugin->whenDatabaseReady();

		return $plugin;
	}

}
