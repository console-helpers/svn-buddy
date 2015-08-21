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


use aik099\SVNBuddy\Exception\CommandException;
use aik099\SVNBuddy\Helper\ContainerHelper;
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
	 * Revision log factory.
	 *
	 * @var RevisionLogFactory
	 */
	private $_revisionLogFactory;

	/**
	 * Command input.
	 *
	 * @var InputInterface
	 */
	protected $input = null;

	/**
	 * Command output.
	 *
	 * @var OutputInterface
	 */
	protected $output = null;

	/**
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;

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

		return $cleanup_command->run($input, $this->output);
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
				throw new CommandException('The "' . $bug_id . '" bug have no associated revisions');
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
	 * Return working copy path.
	 *
	 * @return string
	 */
	protected function getWorkingCopyPath()
	{
		$path = $this->input->getArgument('path');

		if ( !$this->repositoryConnector->isUrl($path) ) {
			$path = realpath($path);
		}
		elseif ( !$this->pathAcceptsUrl ) {
			throw new \RuntimeException('The "path" argument must be a working copy path and not URL.');
		}

		return $path;
	}

}
