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


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Updater\Stability;
use ConsoleHelpers\SVNBuddy\Updater\UpdateManager;
use ConsoleHelpers\SVNBuddy\Updater\Updater;
use ConsoleHelpers\SVNBuddy\Updater\VersionUpdateStrategy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends AbstractCommand
{

	/**
	 * Update manager.
	 *
	 * @var UpdateManager
	 */
	private $_updateManager;

	/**
	 * Process factory.
	 *
	 * @var ProcessFactory
	 */
	private $_processFactory;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('self-update')
			->setDescription(
				'Update application to most recent version'
			)
			->addOption(
				'rollback',
				'r',
				InputOption::VALUE_NONE,
				'Revert to an older version of the application'
			)
			->addOption(
				'stable',
				null,
				InputOption::VALUE_NONE,
				'Force an update to the stable channel'
			)
			->addOption(
				'snapshot',
				null,
				InputOption::VALUE_NONE,
				'Force an update to the snapshot channel'
			)
			->addOption(
				'preview',
				null,
				InputOption::VALUE_NONE,
				'Force an update to the preview channel'
			)
			->addOption(
				'check',
				null,
				InputOption::VALUE_NONE,
				'Checks for update availability'
			);

		parent::configure();
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

		$this->_updateManager = $container['update_manager'];
		$this->_processFactory = $container['process_factory'];
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
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->changeUpdateChannel();

		if ( $this->io->getOption('rollback') ) {
			$this->processRollback();
		}
		elseif ( $this->io->getOption('check') ) {
			$this->processCheck();
		}
		else {
			$this->processUpdate();
		}
	}

	/**
	 * Changes update channel.
	 *
	 * @return void
	 */
	protected function changeUpdateChannel()
	{
		if ( $this->io->getOption('stable') ) {
			$this->_updateManager->setUpdateChannel(Stability::STABLE);
		}

		if ( $this->io->getOption('snapshot') ) {
			$this->_updateManager->setUpdateChannel(Stability::SNAPSHOT);
		}

		if ( $this->io->getOption('preview') ) {
			$this->_updateManager->setUpdateChannel(Stability::PREVIEW);
		}
	}

	/**
	 * Processes rollback.
	 *
	 * @return void
	 * @throws CommandException When rollback failed.
	 */
	protected function processRollback()
	{
		$updater = new Updater(null, false);

		// To get all needed classes autoloaded before phar gets overwritten.
		$this->getFreshVersion();

		if ( !$updater->rollback() ) {
			throw new CommandException('Failed to restore previous version.');
		}

		$this->io->writeln(
			'Rolling back to version <info>' . $this->getFreshVersion() . '</info>.'
		);
	}

	/**
	 * Returns fresh application version.
	 *
	 * @return string
	 */
	protected function getFreshVersion()
	{
		$output = $this->_processFactory
			->createCommandProcess('list', array('--format=xml'))
			->mustRun()
			->getOutput();

		$xml = new \SimpleXMLElement($output);

		return (string)$xml['version'];
	}

	/**
	 * Processes update.
	 *
	 * @return void
	 */
	protected function processUpdate()
	{
		$updater = $this->_updateManager->getUpdater();

		$this->io->write('Checking for updates ... ');
		$has_update = $updater->hasUpdate();
		$this->io->writeln('done');

		$update_channel = $this->_updateManager->getUpdateChannel();

		if ( !$has_update ) {
			$this->io->writeln(sprintf(
				'<info>You are already using version %s (%s channel).</info>',
				$updater->getNewVersion(),
				$update_channel
			));

			return;
		}

		$this->io->write(
			'Updating to version <info>' . $updater->getNewVersion() . '</info> (' . $update_channel . ' channel) ... '
		);

		$updater->update();
		$this->_updateManager->setNewVersion('');

		$this->io->writeln('done.');

		$this->io->writeln(sprintf(
			'Run <info>%s self-update --rollback</info> to return to version %s',
			$_SERVER['argv'][0],
			$updater->getOldVersion()
		));
	}

	/**
	 * Processes check.
	 *
	 * @return void
	 */
	protected function processCheck()
	{
		$updater = $this->_updateManager->getUpdater();

		$this->io->write('Checking for updates ... ');
		$has_update = $updater->hasUpdate();
		$this->io->writeln('done');

		$this->_updateManager->setNewVersion($has_update ? $updater->getNewVersion() : '');
	}

}
