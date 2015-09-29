<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Command;


use aik099\SVNBuddy\Config\ConfigEditor;
use aik099\SVNBuddy\Config\ConfigSetting;
use aik099\SVNBuddy\Exception\CommandException;
use aik099\SVNBuddy\Helper\ContainerHelper;
use aik099\SVNBuddy\ConsoleIO;
use aik099\SVNBuddy\RepositoryConnector\RepositoryConnector;
use aik099\SVNBuddy\RepositoryConnector\RevisionLog;
use aik099\SVNBuddy\RepositoryConnector\RevisionLogFactory;
use Pimple\Container;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class.
 *
 * @method \Mockery\Expectation shouldReceive(string $name)
 */
abstract class AbstractCommand extends Command implements CompletionAwareInterface
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
	 * @var RepositoryConnector
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
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	protected $io;

	/**
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		// Don't use IO from container, because it contains outer IO which doesn't reflect sub-command calls.
		$this->io = new ConsoleIO($input, $output, $this->getHelperSet());

		$this->prepareDependencies();
	}

	/**
	 * Return possible values for the named option
	 *
	 * @param string            $optionName Option name.
	 * @param CompletionContext $context    Completion context.
	 *
	 * @return array
	 */
	public function completeOptionValues($optionName, CompletionContext $context)
	{
		$this->prepareDependencies();

		return array();
	}

	/**
	 * Return possible values for the named argument
	 *
	 * @param string            $argumentName Argument name.
	 * @param CompletionContext $context      Completion context.
	 *
	 * @return array
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context)
	{
		$this->prepareDependencies();

		return array();
	}

	/**
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{
		$container = $this->getContainer();

		$this->repositoryConnector = $container['repository_connector'];
		$this->_revisionLogFactory = $container['revision_log_factory'];
		$this->workingDirectory = $container['working_directory'];
		$this->_configEditor = $container['config_editor'];
	}

	/**
	 * Runs another command.
	 *
	 * @param string $name      Command name.
	 * @param array  $arguments Arguments.
	 *
	 * @return integer
	 */
	protected function runOtherCommand($name, array $arguments = array())
	{
		$arguments['command'] = $name;
		$cleanup_command = $this->getApplication()->find($name);

		$input = new ArrayInput($arguments);

		return $cleanup_command->run($input, $this->io->getOutput());
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
	 * @return ConfigSetting
	 */
	private function _getConfigSetting($name)
	{
		if ( !($this instanceof IConfigAwareCommand) ) {
			throw new \LogicException('Command does not have any settings.');
		}

		foreach ( $this->getConfigSettings() as $config_setting ) {
			if ( $config_setting->getName() === $name ) {
				if ( $config_setting->isWithinScope(ConfigSetting::SCOPE_WORKING_COPY) ) {
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
			$bugtraq_logregex = $this->repositoryConnector->withCache('1 year')->getProperty(
				'bugtraq:logregex',
				$repository_url
			);
			$this->_revisionLogs[$repository_url] = $this->_revisionLogFactory->getRevisionLog(
				$repository_url,
				$bugtraq_logregex
			);
		}

		return $this->_revisionLogs[$repository_url];
	}

	/**
	 * Returns revisions, associated with bugs.
	 *
	 * @param array  $bugs           Bugs.
	 * @param string $repository_url Repository url.
	 *
	 * @return array
	 * @throws CommandException When one of bugs doesn't have associated revisions.
	 */
	protected function getBugsRevisions(array $bugs, $repository_url)
	{
		$revisions = array();
		$revision_log = $this->getRevisionLog($repository_url);

		foreach ( $bugs as $bug_id ) {
			$bug_revisions = $revision_log->getRevisionsFromBug($bug_id);

			if ( !$bug_revisions ) {
				throw new CommandException('The "' . $bug_id . '" bug have no associated revisions.');
			}

			foreach ( $bug_revisions as $bug_revision ) {
				$revisions[$bug_revision] = true;
			}
		}

		return array_keys($revisions);
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
	 * Returns container.
	 *
	 * @return Container
	 */
	protected function getContainer()
	{
		static $container;

		if ( !isset($container) ) {
			/** @var ContainerHelper $container_helper */
			$container_helper = $this->getHelper('container');

			$container = $container_helper->getContainer();
		}

		return $container;
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
