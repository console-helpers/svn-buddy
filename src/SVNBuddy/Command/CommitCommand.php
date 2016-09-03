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
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommitCommand extends AbstractCommand
{

	const STOP_LINE = '--This line, and those below, will be ignored--';

	/**
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

	/**
	 * Editor.
	 *
	 * @var InteractiveEditor
	 */
	private $_editor;

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

		$this->_revisionListParser = $container['revision_list_parser'];
		$this->_editor = $container['editor'];
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

		$commit_message = $this->buildCommitMessage($wc_path);
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

	/**
	 * Builds a commit message.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	protected function buildCommitMessage($wc_path)
	{
		/*
		 * 3. if it's In-Portal project, then:
		 * - create commit message that:
		 * -- Merge of "{from_path}@{from_rev}" to "{to_path}@{to_rev}".
		 * -- Merge of "in-portal/branches/5.2.x@16189" to "in-portal/branches/5.3.x@16188".
		 * - {from_path} to be determined from list of merged revisions
		 * - {from_rev} - last changed of {from_path} by looking in repo
		 * - {to_path} to be determined from working copy
		 * - {to_rev} - last changed of {to_path} by looking in repo
		 * 4. open interactive editor with auto-generated message
		 */

		$commit_message = '';
		$merged_revisions = $this->getFreshMergedRevisions($wc_path);

		if ( $merged_revisions ) {
			$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
			$repository_url = $this->repositoryConnector->getRootUrl($wc_url);

			foreach ( $merged_revisions as $path => $revisions ) {
				$merged_messages = array();
				$revision_log = $this->getRevisionLog($repository_url . $path);
				$commit_message .= $this->getCommitMessageHeading($wc_url, $path) . PHP_EOL;

				$revisions_data = $revision_log->getRevisionsData('summary', $revisions);

				foreach ( $revisions as $revision ) {
					$merged_messages[] = ' * r' . $revision . ': ' . $revisions_data[$revision]['msg'];
				}

				$merged_messages = array_unique(array_map('trim', $merged_messages));
				$commit_message .= implode(PHP_EOL, $merged_messages) . PHP_EOL;
			}
		}

		$commit_message .= $this->getCommitMessageConflicts();

		return rtrim($commit_message);
	}

	/**
	 * Builds commit message heading.
	 *
	 * @param string $wc_url Working copy url.
	 * @param string $path   Source path for merge operation.
	 *
	 * @return string
	 */
	protected function getCommitMessageHeading($wc_url, $path)
	{
		return 'Merging from ' . ucfirst(basename($path)) . ' to ' . ucfirst(basename($wc_url));
	}

	/**
	 * Returns recent merge conflicts.
	 *
	 * @return string
	 */
	protected function getCommitMessageConflicts()
	{
		$recent_conflicts = $this->getSetting(MergeCommand::SETTING_MERGE_RECENT_CONFLICTS, 'merge');

		if ( !$recent_conflicts ) {
			return '';
		}

		$ret = PHP_EOL . 'Conflicts:' . PHP_EOL;

		foreach ( $recent_conflicts as $conflict_path ) {
			$ret .= ' * ' . $conflict_path . PHP_EOL;
		}

		return $ret;
	}

	/**
	 * Returns list of just merged revisions.
	 *
	 * @param string $wc_path Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function getFreshMergedRevisions($wc_path)
	{
		$final_paths = array();
		$old_paths = $this->getMergedRevisions($wc_path, 'BASE');
		$new_paths = $this->getMergedRevisions($wc_path);

		if ( $old_paths === $new_paths ) {
			return array();
		}

		foreach ( $new_paths as $new_path => $new_merged_revisions ) {
			if ( !isset($old_paths[$new_path]) ) {
				// Merge from new path.
				$final_paths[$new_path] = $this->_revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
			}
			elseif ( $new_merged_revisions != $old_paths[$new_path] ) {
				// Merge on existing path.
				$new_merged_revisions_parsed = $this->_revisionListParser->expandRanges(
					explode(',', $new_merged_revisions)
				);
				$old_merged_revisions_parsed = $this->_revisionListParser->expandRanges(
					explode(',', $old_paths[$new_path])
				);
				$final_paths[$new_path] = array_values(
					array_diff($new_merged_revisions_parsed, $old_merged_revisions_parsed)
				);
			}
		}

		return $final_paths;
	}

	/**
	 * Returns list of merged revisions per path.
	 *
	 * @param string  $wc_path  Merge target: working copy path.
	 * @param integer $revision Revision.
	 *
	 * @return array
	 */
	protected function getMergedRevisions($wc_path, $revision = null)
	{
		$paths = array();

		$merge_info = $this->repositoryConnector->getProperty('svn:mergeinfo', $wc_path, $revision);
		$merge_info = array_filter(explode("\n", $merge_info));

		foreach ( $merge_info as $merge_info_line ) {
			list($path, $revisions) = explode(':', $merge_info_line, 2);
			$paths[$path] = $revisions;
		}

		return $paths;
	}

}
