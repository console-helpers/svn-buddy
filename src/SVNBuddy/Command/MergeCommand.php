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
	 * Usable revisions (either to be merged OR to be unmerged).
	 *
	 * @var array
	 */
	private $_usableRevisions = array();

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
			)
			->addOption(
				'record-only',
				null,
				InputOption::VALUE_NONE,
				'Mark revisions as merged without actually merging them'
			)
			->addOption(
				'reverse',
				null,
				InputOption::VALUE_NONE,
				'Rollback previously merged revisions'
			)
			->addOption(
				'aggregate',
				null,
				InputOption::VALUE_NONE,
				'Aggregate displayed revisions by bugs'
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
		$this->_usableRevisions = $this->getUsableRevisions($source_url, $wc_path);

		if ( ($bugs || $revisions) && !$this->_usableRevisions ) {
			throw new CommandException(\sprintf(
				'Nothing to %s.',
				$this->isReverseMerge() ? 'reverse-merge' : 'merge'
			));
		}

		$this->ensureWorkingCopyWithoutConflicts($source_url, $wc_path);

		if ( $this->shouldUseAll($revisions) ) {
			$revisions = $this->_usableRevisions;
		}
		else {
			if ( $revisions ) {
				$revisions = $this->getDirectRevisions($revisions, $source_url);
			}
			elseif ( $bugs ) {
				$revisions = $this->getRevisionLog($source_url)->find('bugs', $bugs);

				if ( !$revisions ) {
					throw new CommandException('Specified bugs aren\'t mentioned in any of revisions');
				}
			}

			if ( $revisions ) {
				$revisions = array_intersect($revisions, $this->_usableRevisions);

				if ( !$revisions ) {
					throw new CommandException(\sprintf(
						'Requested revisions are %s',
						$this->isReverseMerge() ? 'not yet merged' : 'already merged'
					));
				}
			}
		}

		if ( $revisions ) {
			$this->performMerge($source_url, $wc_path, $revisions);
		}
		elseif ( $this->_usableRevisions ) {
			$this->runOtherCommand('log', array(
				'path' => $source_url,
				'--revisions' => implode(',', $this->_usableRevisions),
				'--with-full-message' => $this->io->getOption('with-full-message'),
				'--with-details' => $this->io->getOption('with-details'),
				'--with-summary' => $this->io->getOption('with-summary'),
				'--aggregate' => $this->io->getOption('aggregate'),
				'--with-merge-oracle' => true,
			));
		}
	}

	/**
	 * Determines if all usable revisions should be processed.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return boolean
	 */
	protected function shouldUseAll(array $revisions)
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

		$working_copy_revisions = $this->repositoryConnector->getWorkingCopyRevisions($wc_path);

		if ( count($working_copy_revisions) > 1 ) {
			$this->io->writeln(
				'<error>Mixed revisions: ' . implode(', ', $working_copy_revisions) . '</error>'
			);
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
	 * Ensures, that there are some usable revisions.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function getUsableRevisions($source_url, $wc_path)
	{
		// Avoid missing revision query progress bar overwriting following output.
		$revision_log = $this->getRevisionLog($source_url);

		$this->io->write(sprintf(
			' * Upcoming %s Status ... ',
			$this->isReverseMerge() ? 'Reverse-merge' : 'Merge'
		));
		$usable_revisions = $this->calculateUsableRevisions($source_url, $wc_path);

		if ( $usable_revisions ) {
			$usable_bugs = $revision_log->getBugsFromRevisions($usable_revisions);
			$error_msg = '<error>%d revision(-s) or %d bug(-s) %s</error>';
			$this->io->writeln(sprintf(
				$error_msg,
				count($usable_revisions),
				count($usable_bugs),
				$this->isReverseMerge() ? 'merged' : 'not merged'
			));
		}
		else {
			$this->io->writeln('<info>Up to date</info>');
		}

		return $usable_revisions;
	}

	/**
	 * Returns usable revisions.
	 *
	 * @param string $source_url Merge source: url.
	 * @param string $wc_path    Merge target: working copy path.
	 *
	 * @return array
	 */
	protected function calculateUsableRevisions($source_url, $wc_path)
	{
		$command = $this->repositoryConnector->getCommand(
			'mergeinfo',
			sprintf(
				'--show-revs %s {%s} {%s}',
				$this->isReverseMerge() ? 'merged' : 'eligible',
				$source_url,
				$wc_path
			)
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
		if ( $this->isReverseMerge() ) {
			rsort($revisions, SORT_NUMERIC);
		}
		else {
			sort($revisions, SORT_NUMERIC);
		}

		$revision_count = count($revisions);

		$used_revision_count = 0;
		$used_revisions = $this->repositoryConnector->getMergedRevisionChanges($wc_path, !$this->isReverseMerge());

		if ( $used_revisions ) {
			$used_revisions = call_user_func_array('array_merge', $used_revisions);
			$used_revision_count = count($used_revisions);
			$revision_count += $used_revision_count;
		}

		$param_string_beginning = '-c ';
		$param_string_ending = '{' . $source_url . '} {' . $wc_path . '}';

		if ( $this->isReverseMerge() ) {
			$param_string_beginning .= '-';
		}

		if ( $this->io->getOption('record-only') ) {
			$param_string_ending = '--record-only ' . $param_string_ending;
		}

		$revision_title_mask = $this->getRevisionTitle($wc_path);

		foreach ( $revisions as $index => $revision ) {
			$command = $this->repositoryConnector->getCommand(
				'merge',
				$param_string_beginning . $revision . ' ' . $param_string_ending
			);

			$progress_bar = $this->createMergeProgressBar($used_revision_count + $index + 1, $revision_count);
			$merge_heading = PHP_EOL . '<fg=white;options=bold>';
			$merge_heading .= '--- $1 ' . \str_replace('{revision}', $revision, $revision_title_mask);
			$merge_heading .= " into '$2' " . $progress_bar . ':</>';

			$command->runLive(array(
				$wc_path => '.',
				'/--- (Merging|Reverse-merging) r' . $revision . " into '([^']*)':/" => $merge_heading,
			));

			$this->_usableRevisions = array_diff($this->_usableRevisions, array($revision));
			$this->ensureWorkingCopyWithoutConflicts($source_url, $wc_path, $revision);
		}

		$this->performCommit();
	}

	/**
	 * Returns revision title.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	protected function getRevisionTitle($wc_path)
	{
		$arcanist_config_file = $wc_path . \DIRECTORY_SEPARATOR . '.arcconfig';

		if ( !\file_exists($arcanist_config_file) ) {
			return '<fg=white;options=underscore>{revision}</> revision';
		}

		$arcanist_config = \json_decode(\file_get_contents($arcanist_config_file), true);

		if ( !\is_array($arcanist_config)
			|| !isset($arcanist_config['repository.callsign'], $arcanist_config['phabricator.uri'])
		) {
			return '<fg=white;options=underscore>{revision}</> revision';
		}

		$revision_title = $arcanist_config['phabricator.uri'];
		$revision_title .= 'r' . $arcanist_config['repository.callsign'] . '{revision}';

		return '<fg=white;options=underscore>' . $revision_title . '</>';
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
			$path_revisions = array_intersect($this->_usableRevisions, $path_revisions);

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

	/**
	 * Determines if merge should be done in opposite direction (unmerge).
	 *
	 * @return boolean
	 */
	protected function isReverseMerge()
	{
		return $this->io->getOption('reverse');
	}

}
