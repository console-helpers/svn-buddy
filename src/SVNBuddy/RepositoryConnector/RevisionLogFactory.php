<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\RepositoryConnector;


use aik099\SVNBuddy\Cache\CacheManager;
use aik099\SVNBuddy\ConsoleIO;

class RevisionLogFactory
{

	/**
	 * Repository connector.
	 *
	 * @var RepositoryConnector
	 */
	private $_repositoryConnector;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Create revision log.
	 *
	 * @param RepositoryConnector $repository_connector Repository connector.
	 * @param CacheManager        $cache_manager        Cache manager.
	 * @param ConsoleIO           $io                   Console IO.
	 */
	public function __construct(
		RepositoryConnector $repository_connector,
		CacheManager $cache_manager,
		ConsoleIO $io
	) {
		$this->_repositoryConnector = $repository_connector;
		$this->_cacheManager = $cache_manager;
		$this->_io = $io;
	}

	/**
	 * Returns revision log for url.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return RevisionLog
	 */
	public function getRevisionLog($repository_url)
	{
		$bugtraq_logregex = $this->_repositoryConnector->withCache('1 year')->getProperty(
			'bugtraq:logregex',
			$repository_url
		);

		return new RevisionLog(
			$repository_url,
			$this->_repositoryConnector,
			$this->_cacheManager,
			new LogMessageParser($bugtraq_logregex),
			$this->_io
		);
	}

}
