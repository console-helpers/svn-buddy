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
use ConsoleHelpers\SVNBuddy\Updater\Stability;
use ConsoleHelpers\SVNBuddy\Updater\Updater;
use ConsoleHelpers\SVNBuddy\Updater\VersionUpdateStrategy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends AbstractCommand
{

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	private $_configEditor;

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

		$this->_configEditor = $container['config_editor'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ( $this->io->getOption('rollback') ) {
			$this->processRollback();
		}
		else {
			$this->processUpdate();
		}
	}

	/**
	 * Returns update channel.
	 *
	 * @return string
	 */
	protected function getUpdateChannel()
	{
		if ( $this->io->getOption('stable') ) {
			$this->_configEditor->set('update-channel', Stability::STABLE);
		}

		if ( $this->io->getOption('snapshot') ) {
			$this->_configEditor->set('update-channel', Stability::SNAPSHOT);
		}

		if ( $this->io->getOption('preview') ) {
			$this->_configEditor->set('update-channel', Stability::PREVIEW);
		}

		return $this->_configEditor->get('update-channel');
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

		if ( !$updater->rollback() ) {
			throw new CommandException('Failed to restore previous version.');
		}

		$this->io->writeln('Rolling back to version <info>2016-05-10_15-21-19-1.1.0</info>.');
	}

	/**
	 * Processes update.
	 *
	 * @return void
	 */
	protected function processUpdate()
	{
		$update_strategy = new VersionUpdateStrategy();
		$update_channel = $this->getUpdateChannel();
		$update_strategy->setStability($update_channel);
		$update_strategy->setCurrentLocalVersion($this->getApplication()->getVersion());

		$updater = new Updater(null, false);
		$updater->setStrategyObject($update_strategy);

		if ( !$updater->hasUpdate() ) {
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

		$this->io->writeln('done.');

		$this->io->writeln(sprintf(
			'Use <info>%s self-update --rollback</info> to return to version %s',
			$_SERVER['argv'][0],
			$updater->getOldVersion()
		));
	}

}
