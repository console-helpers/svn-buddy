<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\Connector;


use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class UrlResolverTest extends TestCase
{

	/**
	 * Repository connector.
	 *
	 * @var ObjectProphecy
	 */
	protected $connector;

	/**
	 * Url resolver.
	 *
	 * @var UrlResolver
	 */
	protected $urlResolver;

	protected function setUp()
	{
		parent::setUp();

		$this->connector = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\Connector\Connector');
		$this->urlResolver = new UrlResolver($this->connector->reveal());
	}

	/**
	 * @dataProvider resolveDataProvider
	 */
	public function testResolve($wc_url, $url_to_resolve, $resolved_url)
	{
		$this->connector->getProjectUrl($wc_url)->willReturn('svn://user@domain.com/path/to/project');

		$this->assertEquals($resolved_url, $this->urlResolver->resolve($wc_url, $url_to_resolve));
	}

	public function resolveDataProvider()
	{
		return array(
			// From trunk.
			'from trunk to trunk' => array(
				'svn://user@domain.com/path/to/project/trunk',
				'trunk',
				'svn://user@domain.com/path/to/project/trunk',
			),
			'from trunk to branch' => array(
				'svn://user@domain.com/path/to/project/trunk',
				'branches/red',
				'svn://user@domain.com/path/to/project/branches/red',
			),
			'from trunk to tag' => array(
				'svn://user@domain.com/path/to/project/trunk',
				'tags/red',
				'svn://user@domain.com/path/to/project/tags/red',
			),
			'from trunk to release' => array(
				'svn://user@domain.com/path/to/project/trunk',
				'releases/red',
				'svn://user@domain.com/path/to/project/releases/red',
			),

			// From branch.
			'from branch to trunk' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'trunk',
				'svn://user@domain.com/path/to/project/trunk',
			),
			'from branch to branch' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'red',
				'svn://user@domain.com/path/to/project/branches/red',
			),
			'from branch to tag' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'tags/red',
				'svn://user@domain.com/path/to/project/tags/red',
			),
			'from branch to release' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'releases/red',
				'svn://user@domain.com/path/to/project/releases/red',
			),

			// From tag.
			'from tag to trunk' => array(
				'svn://user@domain.com/path/to/project/tags/blue',
				'trunk',
				'svn://user@domain.com/path/to/project/trunk',
			),
			'from tag to tag' => array(
				'svn://user@domain.com/path/to/project/tags/blue',
				'red',
				'svn://user@domain.com/path/to/project/tags/red',
			),
			'from tag to branch' => array(
				'svn://user@domain.com/path/to/project/tags/blue',
				'branches/red',
				'svn://user@domain.com/path/to/project/branches/red',
			),
			'from tag to release' => array(
				'svn://user@domain.com/path/to/project/tags/blue',
				'releases/red',
				'svn://user@domain.com/path/to/project/releases/red',
			),

			// From release.
			'from release to trunk' => array(
				'svn://user@domain.com/path/to/project/releases/blue',
				'trunk',
				'svn://user@domain.com/path/to/project/trunk',
			),
			'from release to release' => array(
				'svn://user@domain.com/path/to/project/releases/blue',
				'releases/red',
				'svn://user@domain.com/path/to/project/releases/red',
			),
			'from release to branch' => array(
				'svn://user@domain.com/path/to/project/releases/blue',
				'branches/red',
				'svn://user@domain.com/path/to/project/branches/red',
			),
			'from release to tag' => array(
				'svn://user@domain.com/path/to/project/releases/blue',
				'tags/red',
				'svn://user@domain.com/path/to/project/tags/red',
			),

			// Misc.
			'path from root with /' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'/path/to/folder',
				'svn://user@domain.com/path/to/folder',
			),
			'path from root with ^' => array(
				'svn://user@domain.com/path/to/project/branches/blue',
				'^/path/to/folder',
				'svn://user@domain.com/path/to/folder',
			),
		);
	}

}
