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
use ConsoleHelpers\SVNBuddy\Config\PathsConfigSetting;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AggregateCommand extends AbstractCommand implements IConfigAwareCommand
{

	const SETTING_AGGREGATE_IGNORE = 'aggregate.ignore';

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('aggregate')
			->setDescription(
				'Runs other command sequentially on every working copy on a path'
			)
			->addArgument(
				'sub-command',
				InputArgument::OPTIONAL,
				'Command to execute on each found working copy'
			)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Path to folder with working copies',
				'.'
			)
			->addOption(
				'ignore-add',
				null,
				InputOption::VALUE_REQUIRED,
				'Adds path to ignored directory list'
			)
			->addOption(
				'ignore-remove',
				null,
				InputOption::VALUE_REQUIRED,
				'Removes path to ignored directory list'
			)
			->addOption(
				'ignore-show',
				null,
				InputOption::VALUE_NONE,
				'Show ignored directory list'
			)
			->addOption(
				'recursive',
				null,
				InputOption::VALUE_NONE,
				'Perform deep scan for working copies'
			);

		parent::configure();
	}

	/**
	 * Copies relevant options from supported sub-commands.
	 *
	 * @param Application $application An Application instance.
	 *
	 * @return void
	 */
	public function setApplication(Application $application = null)
	{
		parent::setApplication($application);

		// No application is provided, when this command is disabled.
		if ( !$application ) {
			return;
		}

		$input_definition = $this->getDefinition();

		foreach ( $this->getSubCommands() as $sub_command ) {
			assert($sub_command instanceof IAggregatorAwareCommand);
			$copy_options = $sub_command->getAggregatedOptions();

			if ( !$copy_options ) {
				continue;
			}

			$sub_command_input_definition = $sub_command->getDefinition();

			foreach ( $copy_options as $copy_option_name ) {
				$copy_option = $sub_command_input_definition->getOption($copy_option_name);
				$input_definition->addOption($copy_option);
			}
		}
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

		if ( $argumentName === 'sub-command' ) {
			return $this->getSubCommandNames();
		}

		return $ret;
	}

	/**
	 * Returns available sub command names.
	 *
	 * @return array
	 */
	protected function getSubCommandNames()
	{
		return array_keys($this->getSubCommands());
	}

	/**
	 * Returns available sub commands.
	 *
	 * @return Command[]
	 */
	protected function getSubCommands()
	{
		$ret = array();

		foreach ( $this->getApplication()->all() as $alias => $command ) {
			if ( $command instanceof IAggregatorAwareCommand ) {
				$ret[$alias] = $command;
			}
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When "sub-command" argument not specified.
	 * @throws \RuntimeException When specified sub-command doesn't support aggregation.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ( $this->processIgnoreAdd() || $this->processIgnoreRemove() || $this->processIgnoreShow() ) {
			return;
		}

		$sub_command = $this->io->getArgument('sub-command');

		if ( $sub_command === null ) {
			throw new \RuntimeException('Not enough arguments (missing: "sub-command").');
		}

		if ( !in_array($sub_command, $this->getSubCommandNames()) ) {
			throw new \RuntimeException(
				'The "' . $sub_command . '" sub-command is unknown or doesn\'t support aggregation.'
			);
		}

		$this->runSubCommand($sub_command);
	}

	/**
	 * Adds path to ignored directory list.
	 *
	 * @return boolean
	 * @throws CommandException When directory is already ignored.
	 * @throws CommandException When directory does not exist.
	 */
	protected function processIgnoreAdd()
	{
		$raw_ignore_add = $this->io->getOption('ignore-add');

		if ( $raw_ignore_add === null ) {
			return false;
		}

		$ignored = $this->getIgnored();
		$ignore_add = realpath($this->getRawPath() . '/' . $raw_ignore_add);

		if ( $ignore_add === false ) {
			throw new CommandException('The "' . $raw_ignore_add . '" path does not exist.');
		}

		if ( in_array($ignore_add, $ignored) ) {
			throw new CommandException('The "' . $ignore_add . '" directory is already ignored.');
		}

		$ignored[] = $ignore_add;
		$this->setSetting(self::SETTING_AGGREGATE_IGNORE, $ignored);

		return true;
	}

	/**
	 * Removes path from ignored directory list.
	 *
	 * @return boolean
	 * @throws CommandException When directory is not ignored.
	 */
	protected function processIgnoreRemove()
	{
		$raw_ignore_remove = $this->io->getOption('ignore-remove');

		if ( $raw_ignore_remove === null ) {
			return false;
		}

		$ignored = $this->getIgnored();
		$ignore_remove = realpath($this->getRawPath() . '/' . $raw_ignore_remove);

		if ( $ignore_remove === false ) {
			throw new CommandException('The "' . $raw_ignore_remove . '" path does not exist.');
		}

		if ( !in_array($ignore_remove, $ignored) ) {
			throw new CommandException('The "' . $ignore_remove . '" directory is not ignored.');
		}

		$ignored = array_diff($ignored, array($ignore_remove));
		$this->setSetting(self::SETTING_AGGREGATE_IGNORE, $ignored);

		return true;
	}

	/**
	 * Shows ignored paths.
	 *
	 * @return boolean
	 */
	protected function processIgnoreShow()
	{
		if ( !$this->io->getOption('ignore-show') ) {
			return false;
		}

		$ignored = $this->getIgnored();

		if ( !$ignored ) {
			$this->io->writeln('No paths found in ignored directory list.');

			return true;
		}

		$this->io->writeln(array('Paths in ignored directory list:', ''));

		foreach ( $ignored as $ignored_path ) {
			$this->io->writeln(' * ' . $ignored_path);
		}

		$this->io->writeln('');

		return true;
	}

	/**
	 * Returns ignored paths.
	 *
	 * @return array
	 */
	protected function getIgnored()
	{
		return $this->getSetting(self::SETTING_AGGREGATE_IGNORE);
	}

	/**
	 * Runs sub-command.
	 *
	 * @param string $sub_command_name Sub-command name.
	 *
	 * @return void
	 * @throws \RuntimeException When command was used inside a working copy.
	 */
	protected function runSubCommand($sub_command_name)
	{
		$path = realpath($this->getRawPath());

		if ( $this->repositoryConnector->isWorkingCopy($path) ) {
			throw new \RuntimeException('The "' . $path . '" must not be a working copy.');
		}

		$working_copies = $this->getWorkingCopyPaths($path);
		$working_copy_count = count($working_copies);

		$percent_done = 0;
		$percent_increment = round(100 / count($working_copies), 2);

		$sub_command_arguments = $this->prepareSubCommandArguments($sub_command_name);

		foreach ( $working_copies as $index => $wc_path ) {
			$this->io->writeln(array(
				'',
				'Executing <info>' . $sub_command_name . '</info> command on <info>' . $wc_path . '</info> path',
				'',
			));

			$sub_command_arguments['path'] = $wc_path;

			$this->runOtherCommand($sub_command_name, $sub_command_arguments);

			$this->io->writeln(
				'<info>' . ($index + 1) . ' of ' . $working_copy_count . ' sub-commands completed.</info>'
			);
			$percent_done += $percent_increment;
		}
	}

	/**
	 * Prepares sub-command arguments.
	 *
	 * @param string $sub_command_name Sub-command name.
	 *
	 * @return array
	 */
	protected function prepareSubCommandArguments($sub_command_name)
	{
		$sub_command_arguments = array('path' => '');

		$sub_command = $this->getApplication()->get($sub_command_name);
		assert($sub_command instanceof IAggregatorAwareCommand);

		foreach ( $sub_command->getAggregatedOptions() as $copy_option_name ) {
			$copy_option_value = $this->io->getOption($copy_option_name);

			if ( $copy_option_value ) {
				$sub_command_arguments['--' . $copy_option_name] = $copy_option_value;
			}
		}

		return $sub_command_arguments;
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
		$this->io->write('Looking for working copies ... ');
		$all_working_copies = $this->getWorkingCopiesRecursive($path);
		$working_copies = array_diff($all_working_copies, $this->getIgnored());

		$all_working_copies_count = count($all_working_copies);
		$working_copies_count = count($working_copies);

		if ( $all_working_copies_count != $working_copies_count ) {
			$ignored_suffix = ' (' . ($all_working_copies_count - $working_copies_count) . ' ignored)';
		}
		else {
			$ignored_suffix = '';
		}

		if ( !$working_copies ) {
			$this->io->writeln('<error>None found' . $ignored_suffix . '</error>');

			throw new CommandException('No working copies found at "' . $path . '" path.');
		}

		$this->io->writeln('<info>' . $working_copies_count . ' found' . $ignored_suffix . '</info>');

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

		if ( $this->io->isVerbose() ) {
			$this->io->writeln(
				array('', '<debug>scanning: ' . $path . '</debug>')
			);
		}

		// Recursively scan for working copies.
		if ( $this->io->getOption('recursive') ) {
			foreach ( glob($path . '/*', GLOB_ONLYDIR) as $sub_folder ) {
				if ( $this->isExcludedFolder($sub_folder) ) {
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

		// Detect working copies only in current directly.
		foreach ( glob($path . '/*', GLOB_ONLYDIR) as $sub_folder ) {
			if ( $this->isExcludedFolder($sub_folder) ) {
				continue;
			}

			if ( $this->repositoryConnector->isWorkingCopy($sub_folder) ) {
				$working_copies[] = $sub_folder;
			}
		}

		return $working_copies;
	}

	/**
	 * Determines if given folder should be excluded from processing.
	 *
	 * @param string $sub_folder Sub folder.
	 *
	 * @return boolean
	 */
	protected function isExcludedFolder($sub_folder)
	{
		return file_exists($sub_folder . '/.git')
			|| file_exists($sub_folder . '/CVS')
			|| in_array(basename($sub_folder), array('node_modules', 'vendor'));
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new PathsConfigSetting(self::SETTING_AGGREGATE_IGNORE, '', AbstractConfigSetting::SCOPE_GLOBAL),
		);
	}

}
