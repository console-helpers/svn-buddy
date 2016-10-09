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
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class.
 */
abstract class AbstractCommand extends BaseCommand
{

	/**
	 * Raw path.
	 *
	 * @var string
	 */
	private $_rawPath;

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
	 * Working copy resolver.
	 *
	 * @var WorkingCopyResolver
	 */
	private $_workingCopyResolver = null;

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
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When url was given instead of path.
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->_rawPath = null;
		$output->getFormatter()->setStyle('debug', new OutputFormatterStyle('white', 'magenta'));

		parent::initialize($input, $output);

		// Only apply check for commands, that accept working copy path.
		if ( $input->hasArgument('path') ) {
			if ( !$this->pathAcceptsUrl && $this->repositoryConnector->isUrl($this->getRawPath()) ) {
				throw new \RuntimeException('The "path" argument must be a working copy path and not URL.');
			}
		}
	}

	/**
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{
		parent::prepareDependencies();

		$container = $this->getContainer();

		$this->_workingCopyResolver = $container['working_copy_resolver'];
		$this->repositoryConnector = $container['repository_connector'];
		$this->_revisionLogFactory = $container['revision_log_factory'];
		$this->workingDirectory = $container['working_directory'];
		$this->_configEditor = $container['config_editor'];
	}

	/**
	 * Returns command setting value.
	 *
	 * @param string      $name         Name.
	 * @param string|null $command_name Command name to get settings from instead of current command.
	 *
	 * @return mixed
	 */
	protected function getSetting($name, $command_name = null)
	{
		return $this->_getConfigSetting($name, $command_name)->getValue();
	}

	/**
	 * Sets command setting value.
	 *
	 * @param string      $name         Name.
	 * @param mixed       $value        Value.
	 * @param string|null $command_name Command name to get settings from instead of current command.
	 *
	 * @return void
	 */
	protected function setSetting($name, $value, $command_name = null)
	{
		$this->_getConfigSetting($name, $command_name)->setValue($value);
	}

	/**
	 * Validates command setting usage.
	 *
	 * @param string      $name         Name.
	 * @param string|null $command_name Command name to get settings from instead of current command.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When command don't have any config settings to provide.
	 * @throws \LogicException When config setting is not found.
	 */
	private function _getConfigSetting($name, $command_name = null)
	{
		// By default access own config settings.
		if ( !isset($command_name) ) {
			$command_name = $this->getName();
		}

		/** @var IConfigAwareCommand $command */
		$command = $this->getApplication()->get($command_name);

		if ( !($command instanceof IConfigAwareCommand) ) {
			throw new \LogicException('The "' . $command_name . '" command does not have any settings.');
		}

		foreach ( $command->getConfigSettings() as $config_setting ) {
			if ( $config_setting->getName() === $name ) {
				if ( $config_setting->isWithinScope(AbstractConfigSetting::SCOPE_WORKING_COPY) ) {
					$config_setting->setWorkingCopyUrl($this->getWorkingCopyUrl());
				}

				$config_setting->setEditor($this->_configEditor);

				return $config_setting;
			}
		}

		throw new \LogicException('The "' . $command_name . '" command doesn\'t have "' . $name . '" config setting.');
	}

	/**
	 * Prepare setting prefix.
	 *
	 * @param boolean $is_global Return global setting prefix.
	 *
	 * @return string
	 * @todo   Possibly not in use.
	 */
	protected function getConfigScope($is_global)
	{
		if ( $is_global ) {
			return 'global-settings.';
		}

		return 'path-settings[' . $this->getWorkingCopyUrl() . '].';
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
		return $this->_revisionLogFactory->getRevisionLog($repository_url, $this->io);
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
		return $this->_workingCopyResolver->getWorkingCopyUrl($this->getRawPath());
	}

	/**
	 * Return working copy path.
	 *
	 * @return string
	 */
	protected function getWorkingCopyPath()
	{
		return $this->_workingCopyResolver->getWorkingCopyPath($this->getRawPath());
	}

	/**
	 * Returns all refs.
	 *
	 * @return array
	 */
	protected function getAllRefs()
	{
		$wc_url = $this->getWorkingCopyUrl();
		$revision_log = $this->getRevisionLog($wc_url);

		return $revision_log->find('refs', 'all_refs');
	}

	/**
	 * Returns working copy path as used specified it.
	 *
	 * @return string
	 */
	protected function getRawPath()
	{
		if ( !isset($this->_rawPath) ) {
			// FIXME: During auto-complete working copy at CWD is used regardless of given path.
			if ( !isset($this->io) ) {
				$this->_rawPath = '.';
			}
			else {
				$this->_rawPath = $this->io->getArgument('path');
			}
		}

		return $this->_rawPath;
	}

}
