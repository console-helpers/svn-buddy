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
use ConsoleHelpers\SVNBuddy\Config\ChoiceConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\StringConfigSetting;
use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Helper\OutputHelper;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\AbstractMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MergeCommand extends AbstractCommand implements IAggregatorAwareCommand, IConfigAwareCommand
{

	const SETTING_MERGE_SOURCE_URL = 'merge.source-url';

	const SETTING_MERGE_RECENT_CONFLICTS = 'merge.recent-conflicts';

	const SETTING_MERGE_AUTO_COMMIT = 'merge.auto-commit';

	const REVISION_ALL = 'all';

	/**
	 * Merge source detector.
	 *
	 * @var AbstractMergeSourceDetector
	 */
	private $_mergeSourceDetector;

	/**
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

	/**
	 * Unmerged revisions.
	 *
	 * @var array
	 */
	private $_unmergedRevisions = array();

	/**
	 * Url resolver.
	 *
	 * @var UrlResolver
	 */
	private $_urlResolver;

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

		$this->_mergeSourceDetector = $container['merge_source_detector'];
		$this->_revisionListParser = $container['revision_list_parser'];
		$this->_urlResolver = $container['repository_url_resolver'];
		$this->_workingCopyConflictTracker = $container['working_copy_conflict_tracker'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('merge')
			->setDescription('Merge changes from another project or ref within same project into a working copy')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'source-url',
				null,
				InputOption::VALUE_REQUIRED,
				'Merge source url (absolute or relative) or ref name, e.g. <comment>branches/branch-name</comment>'
			)
			->addOption(
				'revisions',
				'r',
				InputOption::VALUE_REQUIRED,
				'List of revision(-s) and/or revision range(-s) to merge, e.g. <comment>53324</comment>, <comment>1224-4433</comment> or <comment>all</comment>'
			)
			->addOption(
				'bugs',
				'b',
				InputOption::VALUE_REQUIRED,
				'List of bug(-s) to merge, e.g. <comment>JRA-1234</comment>, <comment>43644</comment>'
			)
			->addOption(
				'with-full-message',
				'f',
				InputOption::VALUE_NONE,
				'Shows non-truncated commit messages'
			)
			->addOption(
				'with-details',
				'd',
				InputOption::VALUE_NONE,
				'Shows detailed revision information, e.g. paths affected'
			)
			->addOption(
				'with-summary',
				's',
				InputOption::VALUE_NONE,
				'Shows number of added/changed/removed paths in the revision'
			)
			->addOption(
				'update-revision',
				null,
				InputOption::VALUE_REQUIRED,
				'Update working copy to given revision before performing a merge'
			)
			->addOption(
				'auto-commit',
				null,
				InputOption::VALUE_REQUIRED,
				'Automatically perform commit on successful merge, e.g. <comment>yes</comment> or <comment>no</comment>'
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

		if ( $optionName === 'revisions' ) {
			return array('all');
		}

		if ( $optionName === 'source-url' ) {
			return $this->getAllRefs();
		}

		if ( $optionName === 'auto-commit' ) {
			return array('yes', 'no');
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When both "--bugs" and "--revisions" options were specified.
	 * @throws CommandException When everything is merged.
	 * @throws CommandException When manually specified revisions are already merged.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$bugs = $this->getList($this->io->getOption('bugs'));
		$revisions = $this->getList($this->io->getOption('revisions'));

		if ( $bugs && $revisions ) {
			throw new \RuntimeException('The "--bugs" and "--revisions" options are mutually exclusive.');
		}

		$wc_path = $this->getWorkingCopyPath();

		$this->ensureLatestWorkingCopy($wc_path);

		$source_url = $this->getSourceUrl($wc_path);
		$this->printSourceAndTarget($source_url, $wc_path);
		$this->_unmergedRevisions = $this->getUnmergedRevisions($source_url, $wc_path);

		if ( ($bugs || $revisions) && !$this->_unmergedRevisions ) {
			throw new CommandException('Nothing to merge.');
		}

		$this->ensureWorkingCopyWithoutConflicts($source_url, $wc_path);

		if ( $this->shouldMergeAll($revisions) ) {
			$revisions = $this->_unmergedRevisions;
		}
		else {
			if ( $revisions ) {
				$revisions = $this->getDirectRevisions($revisions, $source_url);
			}
			elseif ( $bugs ) {
				$revisions = $this->getRevisionLog($source_url)->find('bugs', $bugs);
			}

			if ( $revisions ) {
				$revisions = array_intersect($revisions, $this->_unmergedRevisions);

				if ( !$revisions ) {
					throw new CommandException('Requested revisions are already merged');
				}
			}
		}

		if ( $revisions ) {
			$this->performMerge($source_url, $wc_path, $revisions);
		}
		elseif ( $this->_unmergedRevisions ) {
			$this->runOtherCommand('log', array(
				'path' => $source_url,
				'--revisions' => implode(',', $this->_unmergedRevisions),
				'--with-full-message' => $this->io->getOption('with-full-message'),
				'--with-details' => $this->io->getOption('with-details'),
				'--with-summary' => $this->io->getOption('with-summary'),
				'--with-merge-oracle' => true,
			));
		}
	}

	/**
	 * Determines if all unmerged revisions should be merged.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return boolean
	 */
	protected function shouldMergeAll(array $revisions)
	{
		return $revisions === array(self::REVISION_ALL);
	}

	/**
	 * Ensures, that working copy is up to date.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 */
	protected function ensureLatestWorkingCopy($wc_path)
	{
		$this->io->write(' * Working Copy Status ... ');
		$update_revision = $this->io->getOption('update-revision');

		if ( $this->repositoryConnector->getWorkingCopyMissing($wc_path) ) {
			$this->io->writeln('<error>Locally deleted files found</error>');
			$this->updateWorkingCopy($wc_path, $update_revision);

			return;
		}

		if ( $this->repositoryConnector->isMixedRevisionWorkingCopy($wc_path) ) {
			$this->io->writeln('<error>Mixed revisions</error>');
			$this->updateWorkingCopy($wc_path, $update_revision);

			return;
		}

		$update_revision = $this->getWorkingCopyUpdateRevision($wc_path);

		if ( isset($update_revision) ) {
			$this->io->writeln('<error>Not at ' . $update_revision . ' revision</error>');
			$this->updateWorkingCopy($wc_path, $update_revision);

			return;
		}

		$this->io->writeln('<info>Up to date</info>');
	}

	/**
	 * Returns revision, that working copy needs to be updated to.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return integer|null
	 */
	protected function getWorkingCopyUpdateRevision($wc_path)
	{
		$update_revision = $this->io->getOption('update-revision');
		$actual_revision = $this->repositoryConnector->getLastRevision($wc_path);

		if ( isset($update_revision) ) {
			if ( is_numeric($update_revision) ) {
				return (int)$update_revision === (int)$actual_revision ? null : $update_revision;
			}

			return $update_revision;
		}

		$repository_revision = $this->repositoryConnector->getLastRevision(
			$this->repositoryConnector->getWorkingCopyUrl($wc_path)
		);

		return $repository_revision > $actual_revision ? $repository_revision : null;
	}

	/**
	 * Updates working copy.
	 *
	 * @param string     $wc_path  Working copy path.
	 * @param mixed|null $revision Revision.
	 *
	 * @return void
	 */
	protected function updateWorkingCopy($wc_path, $revision = null)
	{
		$arguments = array('path' => $wc_path, '--ignore-externals' => true);

		if ( isset($revision) ) {
			$arguments['--revision'] = $revision;
		}

		$this->runOtherCommand('update', $arguments);
	}

	/**
	 * Returns source url for merge.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 * @throws CommandException When source path is invalid.
	 */
	protected function getSourceUrl($wc_path)
	{
		$source_url = $this->io->getOption('source-url');

		if ( $source_url === null ) {
			$source_url = $this->getSetting(self::SETTING_MERGE_SOURCE_URL);
		}
		elseif ( !$this->repositoryConnector->isUrl($source_url) ) {
			$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
			$source_url = $this->_urlResolver->resolve($wc_url, $source_url);
		}

		if ( !$source_url ) {
			$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
			$source_url = $this->_mergeSourceDetector->detect($wc_url);

			if ( $source_url ) {
				$this->setSetting(self::SETTING_MERGE_SOURCE_URL, $source_url);
			}
		}

		if ( !$source_url ) {
			$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
			$error_msg = 'Unable to guess "--source-url" option value. Please specify it manually.' . PHP_EOL;
			$error_msg .= 'Working Copy URL: ' . $wc_url . '.';
			throw new CommandException($error_msg);
		}

		return $source_url;
	}

	/**
	 * Prints information about merge source & target.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 *
	 * @return void
	 */
	protected function printSourceAndTarget($source_url, $wc_path)
	{
		$relative_source_url = $this->repositoryConnector->getRelativePath($source_url);
		$relative_target_url = $this->repositoryConnector->getRelativePath($wc_path);

		$this->io->writeln(' * Merge Source ... <info>' . $relative_source_url . '</info>');
		$this->io->writeln(' * Merge Target ... <info>' . $relative_target_url . '</info>');
	}

	/**
	 * Ensures, that there are some unmerged revisions.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function getUnmergedRevisions($source_url, $wc_path)
	{
		// Avoid missing revision query progress bar overwriting following output.
		$revision_log = $this->getRevisionLog($source_url);

		$this->io->write(' * Upcoming Merge Status ... ');
		$unmerged_revisions = $this->calculateUnmergedRevisions($source_url, $wc_path);

		if ( $unmerged_revisions ) {
			$unmerged_bugs = $revision_log->getBugsFromRevisions($unmerged_revisions);
			$error_msg = '<error>%d revision(-s) or %d bug(-s) not merged</error>';
			$this->io->writeln(sprintf($error_msg, count($unmerged_revisions), count($unmerged_bugs)));
		}
		else {
			$this->io->writeln('<info>Up to date</info>');
		}

		return $unmerged_revisions;
	}

	/**
	 * Returns not merged revisions.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function calculateUnmergedRevisions($source_url, $wc_path)
	{
		$command = $this->repositoryConnector->getCommand(
			'mergeinfo',
			'--show-revs eligible {' . $source_url . '} {' . $wc_path . '}'
		);

		$merge_info = $this->repositoryConnector->getProperty('svn:mergeinfo', $wc_path);

		$cache_invalidator = array(
			'source:' . $this->repositoryConnector->getLastRevision($source_url),
			'merged_hash:' . crc32($merge_info),
		);
		$command->setCacheInvalidator(implode(';', $cache_invalidator));

		$merge_info = $command->run();
		$merge_info = explode(PHP_EOL, $merge_info);

		foreach ( $merge_info as $index => $revision ) {
			$merge_info[$index] = ltrim($revision, 'r');
		}

		return array_filter($merge_info);
	}

	/**
	 * Parses information from "svn:mergeinfo" property.
	 *
	 * @param string $source_path Merge source: path in repository.
	 * @param string $wc_path     Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function getMergedRevisions($source_path, $wc_path)
	{
		$merge_info = $this->repositoryConnector->getProperty('svn:mergeinfo', $wc_path);
		$merge_info = array_filter(explode("\n", $merge_info));

		foreach ( $merge_info as $merge_info_line ) {
			list($path, $revisions) = explode(':', $merge_info_line, 2);

			if ( $path === $source_path ) {
				return $this->_revisionListParser->expandRanges(explode(',', $revisions));
			}
		}

		return array();
	}

	/**
	 * Validates revisions to actually exist.
	 *
	 * @param array  $revisions      Revisions.
	 * @param string $repository_url Repository url.
	 *
	 * @return array
	 * @throws CommandException When revision doesn't exist.
	 */
	protected function getDirectRevisions(array $revisions, $repository_url)
	{
		$revision_log = $this->getRevisionLog($repository_url);

		try {
			$revisions = $this->_revisionListParser->expandRanges($revisions);
			$revision_log->getRevisionsData('summary', $revisions);
		}
		catch ( \InvalidArgumentException $e ) {
			throw new CommandException($e->getMessage());
		}

		return $revisions;
	}

	/**
	 * Performs merge.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 * @param array  $revisions  Revisions to merge.
	 *
	 * @return void
	 */
	protected function performMerge($source_url, $wc_path, array $revisions)
	{
		sort($revisions, SORT_NUMERIC);
		$revision_count = count($revisions);

		$merged_revision_count = 0;
		$merged_revisions = $this->repositoryConnector->getFreshMergedRevisions($wc_path);

		if ( $merged_revisions ) {
			$merged_revisions = call_user_func_array('array_merge', $merged_revisions);
			$merged_revision_count = count($merged_revisions);
			$revision_count += $merged_revision_count;
		}

		foreach ( $revisions as $index => $revision ) {
			$command = $this->repositoryConnector->getCommand(
				'merge',
				'-c ' . $revision . ' {' . $source_url . '} {' . $wc_path . '}'
			);

			$progress_bar = $this->createMergeProgressBar($merged_revision_count + $index + 1, $revision_count);
			$merge_heading = PHP_EOL . '<fg=white;options=bold>';
			$merge_heading .= '--- Merging <fg=white;options=underscore>' . $revision . '</> revision';
			$merge_heading .= " into '$1' " . $progress_bar . ':</>';

			$command->runLive(array(
				$wc_path => '.',
				'/^--- Merging r' . $revision . " into '([^']*)':$/" => $merge_heading,
			));

			$this->_unmergedRevisions = array_diff($this->_unmergedRevisions, array($revision));
			$this->ensureWorkingCopyWithoutConflicts($source_url, $wc_path, $revision);
		}

		$this->performCommit();
	}

	/**
	 * Create merge progress bar.
	 *
	 * @param integer $current Current.
	 * @param integer $total   Total.
	 *
	 * @return string
	 */
	protected function createMergeProgressBar($current, $total)
	{
		$total_length = 28;
		$percent_used = floor(($current / $total) * 100);
		$length_used = floor(($total_length * $percent_used) / 100);
		$length_free = $total_length - $length_used;

		$ret = $length_used > 0 ? str_repeat('=', $length_used - 1) : '';
		$ret .= '>' . str_repeat('-', $length_free);

		return '[' . $ret . '] ' . $percent_used . '% (' . $current . ' of ' . $total . ')';
	}

	/**
	 * Ensures, that there are no unresolved conflicts in working copy.
	 *
	 * @param string  $source_url                 Source url.
	 * @param string  $wc_path                    Working copy path.
	 * @param integer $largest_suggested_revision Largest revision, that is suggested in error message.
	 *
	 * @return void
	 * @throws CommandException When merge conflicts detected.
	 */
	protected function ensureWorkingCopyWithoutConflicts($source_url, $wc_path, $largest_suggested_revision = null)
	{
		$this->io->write(' * Previous Merge Status ... ');

		$conflicts = $this->_workingCopyConflictTracker->getNewConflicts($wc_path);

		if ( !$conflicts ) {
			$this->io->writeln('<info>Successful</info>');

			return;
		}

		$this->_workingCopyConflictTracker->add($wc_path);
		$this->io->writeln('<error>' . count($conflicts) . ' conflict(-s)</error>');

		$table = new Table($this->io->getOutput());

		if ( $largest_suggested_revision ) {
			$table->setHeaders(array(
				'Path',
				'Associated Revisions (before ' . $largest_suggested_revision . ')',
			));
		}
		else {
			$table->setHeaders(array(
				'Path',
				'Associated Revisions',
			));
		}

		$revision_log = $this->getRevisionLog($source_url);
		$source_path = $this->repositoryConnector->getRelativePath($source_url) . '/';

		/** @var OutputHelper $output_helper */
		$output_helper = $this->getHelper('output');

		foreach ( $conflicts as $conflict_path ) {
			$path_revisions = $revision_log->find('paths', $source_path . $conflict_path);
			$path_revisions = array_intersect($this->_unmergedRevisions, $path_revisions);

			if ( $path_revisions && isset($largest_suggested_revision) ) {
				$path_revisions = $this->limitRevisions($path_revisions, $largest_suggested_revision);
			}

			$table->addRow(array(
				$conflict_path,
				$path_revisions ? $output_helper->formatArray($path_revisions, 4) : '-',
			));
		}

		$table->render();

		throw new CommandException('Working copy contains unresolved merge conflicts.');
	}

	/**
	 * Returns revisions not larger, then given one.
	 *
	 * @param array   $revisions    Revisions.
	 * @param integer $max_revision Maximal revision.
	 *
	 * @return array
	 */
	protected function limitRevisions(array $revisions, $max_revision)
	{
		$ret = array();

		foreach ( $revisions as $revision ) {
			if ( $revision < $max_revision ) {
				$ret[] = $revision;
			}
		}

		return $ret;
	}

	/**
	 * Performs commit unless user doesn't want it.
	 *
	 * @return void
	 */
	protected function performCommit()
	{
		$auto_commit = $this->io->getOption('auto-commit');

		if ( $auto_commit !== null ) {
			$auto_commit = $auto_commit === 'yes';
		}
		else {
			$auto_commit = (boolean)$this->getSetting(self::SETTING_MERGE_AUTO_COMMIT);
		}

		if ( $auto_commit ) {
			$this->io->writeln(array('', 'Commencing automatic commit after merge ...'));
			$this->runOtherCommand('commit');
		}
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new StringConfigSetting(self::SETTING_MERGE_SOURCE_URL, ''),
			new ArrayConfigSetting(self::SETTING_MERGE_RECENT_CONFLICTS, array()),
			new ChoiceConfigSetting(
				self::SETTING_MERGE_AUTO_COMMIT,
				array(1 => 'Yes', 0 => 'No'),
				1
			),
		);
	}

	/**
	 * Returns option names, that makes sense to use in aggregation mode.
	 *
	 * @return array
	 */
	public function getAggregatedOptions()
	{
		return array('with-full-message', 'with-details', 'with-summary');
	}

}
