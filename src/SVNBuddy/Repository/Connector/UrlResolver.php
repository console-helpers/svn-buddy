<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\Connector;


class UrlResolver
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Creates instance of url resolver.
	 *
	 * @param Connector $repository_connector Repository connector.
	 */
	public function __construct(Connector $repository_connector)
	{
		$this->repositoryConnector = $repository_connector;
	}

	/**
	 * Resolves any url into absolute one using given url for missing parts.
	 *
	 * @param string $wc_url         Working copy url.
	 * @param string $url_to_resolve Url to resolve.
	 *
	 * @return string
	 */
	public function resolve($wc_url, $url_to_resolve)
	{
		if ( strpos($url_to_resolve, '/') === false && $url_to_resolve !== 'trunk' ) {
			return dirname($wc_url) . '/' . $url_to_resolve;
		}

		if ( preg_match('#^(/|\^/)(.*)$#', $url_to_resolve, $regs) ) {
			return str_replace(parse_url($wc_url, PHP_URL_PATH), '/' . $regs[2], $wc_url);
		}

		return $this->repositoryConnector->getProjectUrl($wc_url) . '/' . $url_to_resolve;
	}

}
