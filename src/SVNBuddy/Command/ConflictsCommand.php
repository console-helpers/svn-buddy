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
use ConsoleHelpers\SVNBuddy\Config\ArrayConfigSetting;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConflictsCommand extends AbstractCommand implements IAggregatorAwareCommand, IConfigAwareCommand
{

	const SETTING_CONFLICTS_RECORDED_CONFLICTS = 'conflicts.recorded-conflicts';

	const MODE_SHOW = 'show';

	const MODE_ADD = 'add';

	const MODE_REPLACE = 'replace';

	const MODE_ERASE = 'erase';

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	private $_workingCopyConflictTracker;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$mode_string = '<comment>' . implode('</comment>, <comment>', $this->getModes()) . '</comment>';

		$this
			->setName('conflicts')
			->setDescription(
				'Manage recorded conflicts in a working copy'
			)
			->setAliases(array('cf'))
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'mode',
				'm',
				InputOption::VALUE_REQUIRED,
				'Operation mode, e.g. ' . $mode_string,
				self::MODE_SHOW
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

		$this->_workingCopyConflictTracker = $container['working_copy_conflict_tracker'];
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

		if ( $optionName === 'mode' ) {
			return $this->getModes();
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When invalid mode is specified.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$mode = $this->io->getOption('mode');

		if ( !in_array($mode, $this->getModes()) ) {
			throw new \RuntimeException(
				'The "' . $mode . '" mode is unknown.'
			);
		}

		$wc_path = $this->getWorkingCopyPath();

		switch ( $mode ) {
			case self::MODE_SHOW:
				$recorded_conflicts = $this->_workingCopyConflictTracker->getRecordedConflicts($wc_path);

				if ( !$recorded_conflicts ) {
					$this->io->writeln('<info>The working copy doesn\'t have any recorded conflicts.</info>');
				}
				else {
					$this->io->writeln(
						'<error>Recorded Conflicts (' . count($recorded_conflicts) . ' paths):</error>'
					);

					foreach ( $recorded_conflicts as $conflicted_path ) {
						$this->io->writeln(' * ' . $conflicted_path);
					}
				}
				break;

			case self::MODE_ADD:
				$this->_workingCopyConflictTracker->add($wc_path);
				$this->io->writeln('<info>Conflicts updated.</info>');
				break;

			case self::MODE_REPLACE:
				$this->_workingCopyConflictTracker->replace($wc_path);
				$this->io->writeln('<info>Conflicts updated.</info>');
				break;

			case self::MODE_ERASE:
				$this->_workingCopyConflictTracker->erase($wc_path);
				$this->io->writeln('<info>Conflicts erased.</info>');
				break;
		}
	}

	/**
	 * Returns allowed modes.
	 *
	 * @return array
	 */
	protected function getModes()
	{
		return array(self::MODE_SHOW, self::MODE_ADD, self::MODE_REPLACE, self::MODE_ERASE);
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new ArrayConfigSetting(self::SETTING_CONFLICTS_RECORDED_CONFLICTS, array()),
		);
	}

}
