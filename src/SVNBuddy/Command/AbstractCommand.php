<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Command;


use ConsoleHelpers\ConsoleKit\Command\AbstractCommand as BaseCommand;
use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;

/**
 * Base command class.
 */
abstract class AbstractCommand extends BaseCommand
{

	/**
	 * Whatever "path" argument accepts repository urls.
	 *
	 * @var boolean
	 */
	protected $pathAcceptsUrl = false;

	/**
	 * Repository connector
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Working directory.
	 *
	 * @var string
	 */
	protected $workingDirectory = null;

	/**
	 * Revision logs by url
	 *
	 * @var RevisionLog[]
	 */
	private $_revisionLogs = array();

	/**
	 * Working copy paths.
	 *
	 * @var string
	 */
	private $_workingCopyPaths = array();

	/**
	 * Revision log factory.
	 *
	 * @var RevisionLogFactory
	 */
	private $_revisionLogFactory;

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	private $_configEditor;

	/**
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{
		parent::prepareDependencies();

		$container = $this->getContainer();

		$this->repositoryConnector = $container['repository_connector'];
		$this->_revisionLogFactory = $container['revision_log_factory'];
		$this->workingDirectory = $container['working_directory'];
		$this->_configEditor = $container['config_editor'];
	}

	/**
	 * Returns command setting value.
	 *
	 * @param string $name Name.
	 *
	 * @return mixed
	 */
	protected function getSetting($name)
	{
		return $this->_getConfigSetting($name)->getValue();
	}

	/**
	 * Sets command setting value.
	 *
	 * @param string $name  Name.
	 * @param mixed  $value Value.
	 *
	 * @return void
	 */
	protected function setSetting($name, $value)
	{
		$this->_getConfigSetting($name)->setValue($value);
	}

	/**
	 * Validates command setting usage.
	 *
	 * @param string $name Name.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When command attempts to read other command settings.
	 */
	private function _getConfigSetting($name)
	{
		if ( !($this instanceof IConfigAwareCommand) ) {
			throw new \LogicException('Command does not have any settings.');
		}

		foreach ( $this->getConfigSettings() as $config_setting ) {
			if ( $config_setting->getName() === $name ) {
				if ( $config_setting->isWithinScope(AbstractConfigSetting::SCOPE_WORKING_COPY) ) {
					$config_setting->setWorkingCopyUrl($this->getWorkingCopyUrl());
				}

				$config_setting->setEditor($this->_configEditor);

				return $config_setting;
			}
		}

		throw new \LogicException('Command can only access own settings.');
	}

	/**
	 * Prepare setting prefix.
	 *
	 * @param boolean $is_global Return global setting prefix.
	 *
	 * @return string
	 */
	protected function getConfigScope($is_global)
	{
		if ( $is_global ) {
			return 'global-settings.';
		}

		$wc_path = $this->getWorkingCopyPath();
		$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
		$wc_hash = substr(hash_hmac('sha1', $wc_url, 'svn-buddy'), 0, 8);

		return 'path-settings.' . $wc_hash . '.';
	}

	/**
	 * Returns revision log.
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return RevisionLog
	 */
	protected function getRevisionLog($repository_url)
	{
		if ( !isset($this->_revisionLogs[$repository_url]) ) {
			$this->_revisionLogs[$repository_url] = $this->_revisionLogFactory->getRevisionLog($repository_url);
		}

		return $this->_revisionLogs[$repository_url];
	}

	/**
	 * Transforms string into list.
	 *
	 * @param string $string    String.
	 * @param string $separator Separator.
	 *
	 * @return array
	 */
	protected function getList($string, $separator = ',')
	{
		return array_filter(array_map('trim', explode($separator, $string)));
	}

	/**
	 * Returns URL to the working copy.
	 *
	 * @return string
	 */
	protected function getWorkingCopyUrl()
	{
		return $this->repositoryConnector->getWorkingCopyUrl($this->getWorkingCopyPath());
	}

	/**
	 * Return working copy path.
	 *
	 * @return string
	 * @throws \RuntimeException When folder isn't a working copy.
	 */
	protected function getWorkingCopyPath()
	{
		$path = $this->getPath();

		if ( !in_array($path, $this->_workingCopyPaths) ) {
			if ( !$this->repositoryConnector->isUrl($path)
				&& !$this->repositoryConnector->isWorkingCopy($path)
			) {
				throw new \RuntimeException('The "' . $path . '" isn\'t a working copy.');
			}

			$this->_workingCopyPaths[] = $path;
		}

		return $path;
	}

	/**
	 * Return working copy path.
	 *
	 * @return string
	 * @throws \RuntimeException When url was given instead of path.
	 */
	protected function getPath()
	{
		$path = $this->io->getArgument('path');

		if ( !$this->repositoryConnector->isUrl($path) ) {
			$path = realpath($path);
		}
		elseif ( !$this->pathAcceptsUrl ) {
			throw new \RuntimeException('The "path" argument must be a working copy path and not URL.');
		}

		return $path;
	}

}
