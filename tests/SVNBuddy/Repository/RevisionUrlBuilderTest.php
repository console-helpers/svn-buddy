<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionUrlBuilder;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class RevisionUrlBuilderTest extends AbstractTestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy|Connector
	 */
	protected $connector;

	/**
	 * Revision URL builder.
	 *
	 * @var RevisionUrlBuilder
	 */
	protected $revisionUrlBuilder;

	/**
	 * @before
	 * @return void
	 */
	protected function setupTest()
	{
		$this->connector = $this->prophesize(Connector::class);
		$this->revisionUrlBuilder = new RevisionUrlBuilder($this->connector->reveal(), 'repo-url');
	}

	/**
	 * @dataProvider withoutUrlStyleDataProvider
	 */
	public function testGetMaskWithWithoutArcanistConfig($style, $expected)
	{
		$this->connector->getFileContent('repo-url/.arcconfig', 'HEAD')
			->willThrow(new RepositoryCommandException('repo command', 'error output'));

		$this->assertEquals($expected, $this->revisionUrlBuilder->getMask($style));
	}

	/**
	 * @dataProvider withoutUrlStyleDataProvider
	 */
	public function testGetMaskWithWithMalformedArcanistConfig($style, $expected)
	{
		$this->connector->getFileContent('repo-url/.arcconfig', 'HEAD')->willReturn('la-la');
		$this->assertEquals($expected, $this->revisionUrlBuilder->getMask($style));

		$this->connector->getFileContent('repo-url/.arcconfig', 'HEAD')->willReturn('{"key":"value"}');
		$this->assertEquals($expected, $this->revisionUrlBuilder->getMask($style));
	}

	public static function withoutUrlStyleDataProvider()
	{
		return array(
			array('', '{revision}'),
			array('style', '<style>{revision}</>'),
		);
	}

	/**
	 * @dataProvider withUrlStyleDataProvider
	 */
	public function testGetMaskWithWithValidArcanistConfig($style, $expected)
	{
		$this->connector->getFileContent('repo-url/.arcconfig', 'HEAD')->willReturn(json_encode(array(
			'repository.callsign' => 'ABC',
			'phabricator.uri' => 'https://test.com/',
		)));
		$this->assertEquals($expected, $this->revisionUrlBuilder->getMask($style));
	}

	public static function withUrlStyleDataProvider()
	{
		return array(
			array('', 'https://test.com/rABC{revision}'),
			array('style', '<style>https://test.com/rABC{revision}</>'),
		);
	}

}
