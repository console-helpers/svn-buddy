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
use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Config\CommandConfig;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;
use ConsoleHelpers\SVNBuddy\Updater\UpdateManager;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class.
 */
abstract class AbstractCommand extends BaseCommand
{

	/**
	 * Command depth.
	 *
	 * @var integer
	 */
	private static $_commandDepth = 0;

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
	 * Command config.
	 *
	 * @var CommandConfig
	 */
	private $_commandConfig;

	/**
	 * Update manager.
	 *
	 * @var UpdateManager
	 */
	private $_updateManager;

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

		if ( $this->checkForAppUpdates($input) ) {
			$this->_showAppUpdateBanner($output);
		}
	}

	/**
	 * Allow showing update banner.
	 *
	 * @param InputInterface $input Input.
	 *
	 * @return boolean
	 */
	protected function checkForAppUpdates(InputInterface $input)
	{
		// Show update banner only for outer command invoked by user and not sub-commands.
		return $input instanceof ArgvInput;
	}

	/**
	 * Shows application update banner.
	 *
	 * @param OutputInterface $output Output.
	 *
	 * @return void
	 */
	private function _showAppUpdateBanner(OutputInterface $output)
	{
		$new_version = $this->_updateManager->getNewVersion();

		if ( !strlen($new_version) ) {
			return;
		}

		$message = sprintf(
			'  Update available. Run "%s self-update" to upgrade.  ',
			$_SERVER['argv'][0]
		);
		$line_length = mb_strlen($message);

		$output->writeln(array(
			'<fg=white;bg=blue>' . str_repeat(' ', $line_length) . '</>',
			'<fg=white;bg=blue>' . $message . '</>',
			'<fg=white;bg=blue>' . str_repeat(' ', $line_length) . '</>',
			'',
		));
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
		$this->_commandConfig = $container['command_config'];
		$this->_updateManager = $container['update_manager'];
	}

	/**
	 * @inheritDoc
	 *
	 * @throws CommandException When command exception is caught.
	 */
	public function run(InputInterface $input, OutputInterface $output)
	{
		self::$_commandDepth++;

		try {
			return parent::run($input, $output);
		}
		catch ( CommandException $e ) {
			if ( self::$_commandDepth === 1 ) {
				$this->io->notify();
			}

			throw $e;
		} finally {
			self::$_commandDepth--;
		}
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
		return $this->_commandConfig->getSettingValue($name, $this, $this->getRawPath());
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
		$this->_commandConfig->setSettingValue($name, $this, $this->getRawPath(), $value);
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
