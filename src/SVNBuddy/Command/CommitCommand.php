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
use aik099\SVNBuddy\InteractiveEditor;
use aik099\SVNBuddy\RepositoryConnector\RevisionListParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('commit')
			->setDescription(
				'Sends changes to repository'
			)
			->setHelp($description)
			->setAliases(array('ci'))
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
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
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$conflicts = $this->repositoryConnector->getWorkingCopyConflicts($wc_path);

		if ( $conflicts ) {
			throw new CommandException('Conflicts detected. Please resolve them before committing.');
		}

		$working_copy_status = $this->repositoryConnector->getCompactWorkingCopyStatus($wc_path, false);

		if ( !$working_copy_status ) {
			throw new CommandException('Nothing to commit.');
		}

		$commit_message = $this->buildCommitMessage($wc_path);
		$commit_message .= PHP_EOL . PHP_EOL . self::STOP_LINE . PHP_EOL . PHP_EOL . $working_copy_status;

		$edited_commit_message = $this->_editor
			->setDocumentName('commit_message')
			->setContent($commit_message)
			->launch();

		$stop_line_pos = strpos($edited_commit_message, self::STOP_LINE);

		if ( $stop_line_pos !== false ) {
			$edited_commit_message = trim(substr($edited_commit_message, 0, $stop_line_pos));
		}

		$output->writeln(array('<fg=white;options=bold>Commit message:</>', $edited_commit_message, ''));

		if ( !$this->io->askConfirmation('Run "svn commit"', false) ) {
			throw new CommandException('Commit aborted by user.');
		}

		$tmp_file = tempnam(sys_get_temp_dir(), 'commit_message_');
		file_put_contents($tmp_file, $edited_commit_message);

		$this->repositoryConnector->getCommand('commit', '{' . $wc_path . '} -F {' . $tmp_file . '}')->runLive();
		unlink($tmp_file);

		$output->writeln('<info>Done</info>');
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

		$merged_revisions = $this->getFreshMergedRevisions($wc_path);

		if ( !$merged_revisions ) {
			return '';
		}

		$commit_message = '';
		$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
		$repository_url = $this->removePathFromURL($wc_url);

		foreach ( $merged_revisions as $path => $revisions ) {
			$merged_messages = array();
			$revision_log = $this->getRevisionLog($repository_url . $path);
			$commit_message .= 'Merging from ' . ucfirst(basename($path)) . ' to ' . ucfirst(basename($wc_url)) . PHP_EOL;

			foreach ( $revisions as $revision ) {
				$revision_data = $revision_log->getRevisionData($revision);
				$merged_messages[] = ' * r' . $revision . ': ' . $revision_data['msg'];
			}

			$merged_messages = array_unique(array_map('trim', $merged_messages));
			$commit_message .= implode(PHP_EOL, $merged_messages) . PHP_EOL;
		}

		return rtrim($commit_message);
	}

	/**
	 * Removes path component from URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	protected function removePathFromURL($url)
	{
		$path = parse_url($url, PHP_URL_PATH);

		return preg_replace('#' . preg_quote($path, '#') . '$#', '', $url, 1);
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
				$final_paths[$new_path] = $this->_revisionListParser->expandRanges($new_merged_revisions);
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
