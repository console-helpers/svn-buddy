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


use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\ChoiceConfigSetting;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends AbstractCommand implements IAggregatorAwareCommand, IConfigAwareCommand
{

	const SETTING_UPDATE_AUTO_DEPLOY = 'update.auto-deploy';

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	private $_workingCopyConflictTracker;

	/**
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{
		parent::prepareDependencies();

		$container = $this->getContainer();

		$this->_workingCopyConflictTracker = $container['working_copy_conflict_tracker'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('update')
			->setDescription('Bring changes from the repository into the working copy')
			->setAliases(array('up'))
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'revision',
				'r',
				InputOption::VALUE_REQUIRED,
				'Update working copy to specified revision, e.g. <comment>NUMBER</comment>, <comment>{DATE}</comment>, <comment>HEAD</comment>, <comment>BASE</comment>, <comment>COMMITTED</comment>, <comment>PREV</comment>'
			)
			->addOption(
				'ignore-externals',
				null,
				InputOption::VALUE_NONE,
				'Ignore externals definitions'
			)
			->addOption(
				'auto-deploy',
				null,
				InputOption::VALUE_REQUIRED,
				'Automatically perform local deployment on successful update, e.g. <comment>yes</comment> or <comment>no</comment>'
			);

		parent::configure();
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
		$ret = parent::completeOptionValues($optionName, $context);

		if ( $optionName === 'auto-deploy' ) {
			return array('yes', 'no');
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$revision = $this->io->getOption('revision');
		$ignore_externals = $this->io->getOption('ignore-externals');

		$show_revision = $revision ? $revision : 'HEAD';
		$show_externals = $ignore_externals ? '(excluding externals)' : '(including externals)';
		$this->io->writeln(
			'Updating working copy to <info>' . $show_revision . '</info> revision ' . $show_externals . ' ... '
		);

		$arguments = array($wc_path);

		if ( $revision ) {
			$arguments[] = '--revision';
			$arguments[] = $revision;
		}

		if ( $ignore_externals ) {
			$arguments[] = '--ignore-externals';
		}

		$command = $this->repositoryConnector->getCommand('update', $arguments);
		$command->runLive(array(
			$wc_path => '.',
		));

		if ( $this->_workingCopyConflictTracker->getNewConflicts($wc_path) ) {
			$this->_workingCopyConflictTracker->add($wc_path);
		}

		$this->io->writeln('<info>Done</info>');

		$this->deploy();
	}

	/**
	 * Performs a deploy.
	 *
	 * @return void
	 */
	protected function deploy()
	{
		$auto_deploy = $this->io->getOption('auto-deploy');

		if ( $auto_deploy !== null ) {
			$auto_deploy = $auto_deploy === 'yes';
		}
		else {
			$auto_deploy = (boolean)$this->getSetting(self::SETTING_UPDATE_AUTO_DEPLOY);
		}

		if ( !$auto_deploy ) {
			return;
		}

		$this->runOtherCommand('deploy', array('--local' => true));
	}

	/**
	 * Returns option names, that makes sense to use in aggregation mode.
	 *
	 * @return array
	 */
	public function getAggregatedOptions()
	{
		return array('ignore-externals');
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new ChoiceConfigSetting(
				self::SETTING_UPDATE_AUTO_DEPLOY,
				array(1 => 'Yes', 0 => 'No'),
				1
			),
		);
	}

}
