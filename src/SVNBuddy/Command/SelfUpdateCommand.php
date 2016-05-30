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
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Strategy\ShaStrategy;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends AbstractCommand
{

	const UPDATE_CHANNEL_PREVIEW = 'preview';

	const UPDATE_CHANNEL_SNAPSHOT = 'snapshot';

	const UPDATE_CHANNEL_STABLE = 'stable';

	const UPDATE_SERVER_URL = 'https://svn-buddy-updater.herokuapp.com';

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
	 * Returns update strategy.
	 *
	 * @return StrategyInterface
	 * @throws CommandException When update channel cannot be found.
	 */
	protected function getUpdateStrategy()
	{
		$update_channel = $this->getUpdateChannel();

		if ( $update_channel === self::UPDATE_CHANNEL_STABLE ) {
			$update_strategy = new GithubStrategy();
			$update_strategy->setPackageName('console-helpers/svn-buddy');
			$update_strategy->setPharName('svn-buddy.phar');
			$update_strategy->setCurrentLocalVersion($this->getApplication()->getVersion());

			return $update_strategy;
		}

		$versions = json_decode(
			humbug_get_contents(self::UPDATE_SERVER_URL . '/versions'),
			true
		);

		if ( !isset($versions[$update_channel]) ) {
			throw new CommandException('The "' . $update_channel . '" update channel not found.');
		}

		$update_strategy = new ShaStrategy();
		$update_strategy->setPharUrl(self::UPDATE_SERVER_URL . $versions[$update_channel]['path']);
		$update_strategy->setVersionUrl(self::UPDATE_SERVER_URL . $versions[$update_channel]['path'] . '.sig');

		return $update_strategy;
	}

	/**
	 * Returns update channel.
	 *
	 * @return string
	 */
	protected function getUpdateChannel()
	{
		if ( $this->io->getOption('stable') ) {
			$this->_configEditor->set('update-channel', self::UPDATE_CHANNEL_STABLE);
		}

		if ( $this->io->getOption('snapshot') ) {
			$this->_configEditor->set('update-channel', self::UPDATE_CHANNEL_SNAPSHOT);
		}

		if ( $this->io->getOption('preview') ) {
			$this->_configEditor->set('update-channel', self::UPDATE_CHANNEL_PREVIEW);
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

		$this->io->writeln('Previous version restored.');
	}

	/**
	 * Processes update.
	 *
	 * @return void
	 */
	protected function processUpdate()
	{
		$updater = new Updater(null, false);
		$updater->setStrategyObject($this->getUpdateStrategy());

		if ( !$updater->update() ) {
			$this->io->writeln(
				'Already at latest version (<info>' . $this->getUpdateChannel() . '</info> channel).'
			);
		}
		else {
			$this->io->writeln(
				'Updated to latest version (<info>' . $this->getUpdateChannel() . '</info> channel).'
			);
		}
	}

}
