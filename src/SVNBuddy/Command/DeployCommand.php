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


use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\ArrayConfigSetting;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends AbstractCommand implements IConfigAwareCommand
{

	const SETTING_DEPLOY_REMOTE_COMMANDS = 'deploy.remote-commands';

	const SETTING_DEPLOY_LOCAL_COMMANDS = 'deploy.local-commands';

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('deploy')
			->setDescription('Deploys changes to local/remote server')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'remote',
				'r',
				InputOption::VALUE_NONE,
				'Performs remote deployment'
			)
			->addOption(
				'local',
				'l',
				InputOption::VALUE_NONE,
				'Performs local deployment'
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws CommandException When none of "--remote" and "--local" options aren't specified.
	 * @throws CommandException When deployment commands are not specified.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ( $this->io->getOption('remote') ) {
			$is_remote = true;
			$deploy_commands = $this->getSetting(self::SETTING_DEPLOY_REMOTE_COMMANDS);
		}
		elseif ( $this->io->getOption('local') ) {
			$is_remote = false;
			$deploy_commands = $this->getSetting(self::SETTING_DEPLOY_LOCAL_COMMANDS);
		}
		else {
			throw new CommandException('Please specify either "--remote" or "--local" option.');
		}

		$suffix = $is_remote ? 'remote' : 'local';

		if ( !$deploy_commands ) {
			if ( $this->getApplication()->isTopmostCommand($this) ) {
				throw new CommandException('The ' . $suffix . ' deployment commands are not specified.');
			}

			return;
		}

		$this->io->writeln('<info>Performing a ' . $suffix . ' deploy...</info>');

		foreach ( $deploy_commands as $deploy_command ) {
			$this->io->writeln('<comment>Executing: ' . $deploy_command . '</comment>');
			passthru($deploy_command);
			$this->io->writeln('<comment>Done.</comment>');
			$this->io->writeln('');
		}

		$this->io->writeln('<info>Deployed.</info>');
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new ArrayConfigSetting(
				self::SETTING_DEPLOY_REMOTE_COMMANDS,
				array()
			),
			new ArrayConfigSetting(
				self::SETTING_DEPLOY_LOCAL_COMMANDS,
				array()
			),
		);
	}

}
