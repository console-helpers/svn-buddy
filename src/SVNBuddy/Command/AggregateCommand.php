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
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AggregateCommand extends AbstractCommand
{

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('aggregate')
			->setDescription(
				'Runs other command sequentially on every working copy on a path'
			)
			->setHelp($description)
			->addArgument(
				'sub-command',
				InputArgument::REQUIRED,
				'Command to execute on each found working copy'
			)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Path to folder with working copies',
				'.'
			);

		parent::configure();
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
		$ret = parent::completeArgumentValues($argumentName, $context);

		if ( $argumentName == 'sub-command' ) {
			return $this->getSubCommands();
		}

		return $ret;
	}

	/**
	 * Returns available sub commands.
	 *
	 * @return array
	 */
	protected function getSubCommands()
	{
		$ret = array();

		foreach ( $this->getApplication()->all() as $command ) {
			$ret[] = $command->getName();
		}

		return array_diff($ret, array($this->getName(), '_completion'));
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sub_command = $input->getArgument('sub-command');

		if ( !in_array($sub_command, $this->getSubCommands()) ) {
			throw new \RuntimeException('The "' . $sub_command . '" sub-command is unknown.');
		}

		$path = $this->getWorkingCopyPath();

		if ( $this->repositoryConnector->isWorkingCopy($path) ) {
			throw new \RuntimeException('The "' . $path . '" must not be a working copy.');
		}

		$working_copies = $this->getWorkingCopyPaths($path);
		$working_copy_count = count($working_copies);

		$percent_done = 0;
		$percent_increment = round(100 / count($working_copies), 2);

		foreach ( $working_copies as $index => $wc_path ) {
			$output->writeln(array(
				'',
				'Executing <info>' . $sub_command . '</info> command on <info>' . $wc_path . '</info> path',
				'',
			));

			$sub_command_arguments = array(
				'path' => $wc_path,
			);

			if ( $sub_command === 'merge' ) {
				$sub_command_arguments['--force-update'] = true;
			}

			$this->runOtherCommand($sub_command, $sub_command_arguments);

			$output->writeln(
				'<info>' . ($index + 1) . ' of ' . $working_copy_count . ' sub-commands completed.</info>'
			);
			$percent_done += $percent_increment;
		}
	}

	/**
	 * Returns working copies found at given path.
	 *
	 * @param string $path Path.
	 *
	 * @return array
	 * @throws CommandException When no working copies where found.
	 */
	protected function getWorkingCopyPaths($path)
	{
		$this->output->write('Looking for working copies ... ');
		$working_copies = $this->getWorkingCopiesRecursive($path);

		if ( !$working_copies ) {
			$this->output->writeln('<error>None found</error>');

			throw new CommandException('No working copies found at "' . $path . '" path');
		}

		$this->output->writeln('<info>' . count($working_copies) . ' found</info>');

		return array_values($working_copies);
	}

	/**
	 * Returns working copy locations recursively.
	 *
	 * @param string $path Path.
	 *
	 * @return array
	 */
	protected function getWorkingCopiesRecursive($path)
	{
		$working_copies = array();

		foreach ( glob($path . '/*', GLOB_ONLYDIR) as $sub_folder ) {
			if ( file_exists($sub_folder . '/.git') || file_exists($sub_folder . '/CVS') ) {
				continue;
			}

			if ( $this->repositoryConnector->isWorkingCopy($sub_folder) ) {
				$working_copies[] = $sub_folder;
			}
			else {
				$working_copies = array_merge($working_copies, $this->getWorkingCopiesRecursive($sub_folder));
			}
		}

		return $working_copies;
	}

}
