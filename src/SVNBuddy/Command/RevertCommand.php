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
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevertCommand extends AbstractCommand implements IAggregatorAwareCommand
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
			->setName('revert')
			->setDescription('Restore pristine working copy file (undo most local edits)')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();

		// Collect added path before "svn revert" because then they won't be shown as added by "svn status".
		$added_paths = $this->getAddedPaths();

		$this->io->writeln('Reverting local changes in working copy ... ');
		$command = $this->repositoryConnector->getCommand(
			'revert',
			'--depth infinity {' . $wc_path . '}'
		);
		$command->runLive(array(
			$wc_path => '.',
		));

		$this->deletePaths($added_paths);

		$this->_workingCopyConflictTracker->erase($wc_path);
		$this->io->writeln('<info>Done</info>');
	}

	/**
	 * Returns added paths.
	 *
	 * @return array
	 */
	protected function getAddedPaths()
	{
		$wc_path = $this->getWorkingCopyPath();

		$added_paths = array();
		$status = $this->repositoryConnector->getWorkingCopyStatus($wc_path);

		foreach ( $status as $status_path => $status_path_data ) {
			if ( $status_path_data['item'] === Connector::STATUS_ADDED ) {
				$added_paths[] = $status_path;
			}
		}

		return $added_paths;
	}

	/**
	 * Deletes given paths in the working copy.
	 *
	 * @param array $paths Paths.
	 *
	 * @return void
	 * @throws CommandException When one of the paths can't be deleted.
	 */
	protected function deletePaths(array $paths)
	{
		if ( !$paths ) {
			return;
		}

		// When folder with files is added delete files first and then folder.
		rsort($paths);
		$wc_path = $this->getWorkingCopyPath();

		foreach ( $paths as $path ) {
			$absolute_path = $wc_path . '/' . $path;

			// At some point "svn revert" was improved to do this (maybe after SVN 1.6).
			if ( !file_exists($absolute_path) ) {
				continue;
			}

			$deleted = is_dir($absolute_path) ? rmdir($absolute_path) : unlink($absolute_path);

			if ( $deleted ) {
				$this->io->writeln('Reverted \'' . str_replace($wc_path, '.', $absolute_path) . '\'');
			}
			else {
				throw new CommandException('Unable to delete "' . $path . '".');
			}
		}
	}

}
