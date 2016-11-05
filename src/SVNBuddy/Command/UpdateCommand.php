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


use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends AbstractCommand implements IAggregatorAwareCommand
{

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
				'ignore-externals',
				null,
				InputOption::VALUE_NONE,
				'Ignore externals definitions'
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();

		$this->io->writeln('Updating working copy ... ');

		$param_string = '{' . $wc_path . '}';

		if ( $this->io->getOption('ignore-externals') ) {
			$param_string .= ' --ignore-externals';
		}

		$command = $this->repositoryConnector->getCommand('update', $param_string);
		$command->runLive(array(
			$wc_path => '.',
		));

		if ( $this->_workingCopyConflictTracker->getNewConflicts($wc_path) ) {
			$this->_workingCopyConflictTracker->add($wc_path);
		}

		$this->io->writeln('<info>Done</info>');
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

}
