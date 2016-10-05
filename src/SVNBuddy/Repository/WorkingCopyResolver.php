<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class WorkingCopyResolver
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Working copy paths.
	 *
	 * @var array
	 */
	private $_workingCopyPaths = array();

	/**
	 * Mapping between working copy paths and their urls.
	 *
	 * @var array
	 */
	private $_workingCopyUrlMapping = array();

	/**
	 * Mapping between path to working copy specified by user to one, that is actually used.
	 *
	 * @var array
	 */
	private $_resolvedPathMapping = array();

	/**
	 * Creates working copy resolver instance.
	 *
	 * @param Connector $repository_connector Repository connector.
	 */
	public function __construct(Connector $repository_connector)
	{
		$this->_repositoryConnector = $repository_connector;
	}

	/**
	 * Returns URL to the working copy.
	 *
	 * @param string $raw_path Raw path.
	 *
	 * @return string
	 */
	public function getWorkingCopyUrl($raw_path)
	{
		$wc_path = $this->getWorkingCopyPath($raw_path);

		if ( !isset($this->_workingCopyUrlMapping[$wc_path]) ) {
			$this->_workingCopyUrlMapping[$wc_path] = $this->_repositoryConnector->getWorkingCopyUrl($wc_path);
		}

		return $this->_workingCopyUrlMapping[$wc_path];
	}

	/**
	 * Return working copy path.
	 *
	 * @param string $raw_path Raw path.
	 *
	 * @return string
	 * @throws \LogicException When folder isn't a working copy.
	 */
	public function getWorkingCopyPath($raw_path)
	{
		$path = $this->resolvePath($raw_path);

		if ( !in_array($path, $this->_workingCopyPaths) ) {
			if ( !$this->_repositoryConnector->isUrl($path)
				&& !$this->_repositoryConnector->isWorkingCopy($path)
			) {
				throw new \LogicException('The "' . $path . '" isn\'t a working copy.');
			}

			$this->_workingCopyPaths[] = $path;
		}

		return $path;
	}

	/**
	 * Returns absolute path to working copy from given raw path.
	 *
	 * @param string $raw_path Raw path.
	 *
	 * @return string
	 */
	protected function resolvePath($raw_path)
	{
		$path = $raw_path;

		if ( !isset($this->_resolvedPathMapping[$path]) ) {
			if ( $this->_repositoryConnector->isUrl($path) ) {
				$this->_resolvedPathMapping[$path] = $this->_repositoryConnector->removeCredentials($path);
			}
			else {
				// When deleted path is specified, then use it's existing parent path.
				if ( !file_exists($path) && file_exists(dirname($path)) ) {
					$path = dirname($path);
				}

				$this->_resolvedPathMapping[$path] = realpath($path);
			}
		}

		return $this->_resolvedPathMapping[$path];
	}

}
