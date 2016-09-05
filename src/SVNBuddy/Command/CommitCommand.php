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
use ConsoleHelpers\SVNBuddy\InteractiveEditor;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessageBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommitCommand extends AbstractCommand
{

	const STOP_LINE = '--This line, and those below, will be ignored--';

	/**
	 * Editor.
	 *
	 * @var InteractiveEditor
	 */
	private $_editor;

	/**
	 * Commit message builder.
	 *
	 * @var CommitMessageBuilder
	 */
	private $_commitMessageBuilder;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('commit')
			->setDescription(
				'Send changes from your working copy to the repository'
			)
			->setAliases(array('ci'))
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'cl',
				null,
				InputOption::VALUE_NONE,
				'Operate only on members of selected changelist'
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

		$this->_editor = $container['editor'];
		$this->_commitMessageBuilder = $container['commit_message_builder'];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws CommandException When conflicts are detected.
	 * @throws CommandException Working copy has no changes.
	 * @throws CommandException User decides not to perform a commit.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$conflicts = $this->repositoryConnector->getWorkingCopyConflicts($wc_path);

		if ( $conflicts ) {
			throw new CommandException('Conflicts detected. Please resolve them before committing.');
		}

		$changelist = $this->getChangelist($wc_path);
		$compact_working_copy_status = $this->repositoryConnector->getCompactWorkingCopyStatus($wc_path, $changelist);

		if ( !$compact_working_copy_status ) {
			throw new CommandException('Nothing to commit.');
		}

		$commit_message = $this->_commitMessageBuilder->build(
			$wc_path,
			$changelist,
			$this->getSetting(MergeCommand::SETTING_MERGE_RECENT_CONFLICTS, 'merge')
		);
		$commit_message .= PHP_EOL . PHP_EOL . self::STOP_LINE . PHP_EOL . PHP_EOL . $compact_working_copy_status;

		$edited_commit_message = $this->_editor
			->setDocumentName('commit_message')
			->setContent($commit_message)
			->launch();

		$stop_line_pos = strpos($edited_commit_message, self::STOP_LINE);

		if ( $stop_line_pos !== false ) {
			$edited_commit_message = trim(substr($edited_commit_message, 0, $stop_line_pos));
		}

		$this->io->writeln(array('<fg=white;options=bold>Commit message:</>', $edited_commit_message, ''));

		if ( !$this->io->askConfirmation('Run "svn commit"', false) ) {
			throw new CommandException('Commit aborted by user.');
		}

		$tmp_file = tempnam(sys_get_temp_dir(), 'commit_message_');
		file_put_contents($tmp_file, $edited_commit_message);

		$arguments = array(
			'-F {' . $tmp_file . '}',
		);

		if ( strlen($changelist) ) {
			$arguments[] = '--depth empty';

			// Relative path used to make command line shorter.
			foreach ( array_keys($this->repositoryConnector->getWorkingCopyStatus($wc_path, $changelist)) as $path ) {
				$arguments[] = '{' . $path . '}';
			}
		}
		else {
			$arguments[] = '{' . $wc_path . '}';
		}

		$this->repositoryConnector->getCommand('commit', implode(' ', $arguments))->runLive();
		$this->setSetting(MergeCommand::SETTING_MERGE_RECENT_CONFLICTS, null, 'merge');
		unlink($tmp_file);

		$this->io->writeln('<info>Done</info>');
	}

	/**
	 * Returns user selected changelist.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string|null
	 * @throws CommandException When no changelists found.
	 */
	protected function getChangelist($wc_path)
	{
		if ( !$this->io->getOption('cl') ) {
			return null;
		}

		$changelists = $this->repositoryConnector->getWorkingCopyChangelists($wc_path);

		if ( !$changelists ) {
			throw new CommandException('No changelists detected.');
		}

		return $this->io->choose(
			'Pick changelist by number [0]:',
			$changelists,
			0,
			'Changelist "%s" is invalid.'
		);
	}

}
