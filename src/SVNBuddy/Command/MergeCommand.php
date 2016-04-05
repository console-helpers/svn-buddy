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
use ConsoleHelpers\SVNBuddy\Config\StringConfigSetting;
use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\AbstractMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
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
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('merge')
			->setDescription('Applies the change from another source to a working copy path')
			->setHelp($description)
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
				'Source url (absolute or relative url, same project branch/tag name)'
			)
			->addOption(
				'revisions',
				'r',
				InputOption::VALUE_REQUIRED,
				'Revisions to merge (e.g. "53324,34342,1224-4433,232" or "all" to merge all)'
			)
			->addOption(
				'bugs',
				'b',
				InputOption::VALUE_REQUIRED,
				'Bugs to merge (e.g. "JRA-1234,43644")'
			)
			->addOption(
				'details',
				'd',
				InputOption::VALUE_NONE,
				'Shows paths affected in each revision'
			)
			->addOption(
				'summary',
				's',
				InputOption::VALUE_NONE,
				'Shows summary of paths affected in each revision'
			)
			/*->addOption(
				'rollback',
				null,
				InputOption::VALUE_NONE,
				'Do a rollback merge'
			)
			->addOption(
				'record-only',
				null,
				InputOption::VALUE_NONE,
				'Only mark revisions as merged'
			)*/;

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

		if ( $optionName == 'source-url' ) {
			return $this->getAllRefs();
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
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
				'path' => $this->repositoryConnector->getProjectUrl($source_url),
				'--revisions' => implode(',', $this->_unmergedRevisions),
				'--details' => $this->io->getOption('details'),
				'--summary' => $this->io->getOption('summary'),
				'--merge-oracle' => true,
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
	 * @throws CommandException When working copy is out of date.
	 */
	protected function ensureLatestWorkingCopy($wc_path)
	{
		$this->io->write(' * Working Copy Status ... ');

		if ( $this->repositoryConnector->isMixedRevisionWorkingCopy($wc_path) ) {
			$this->io->writeln('<error>Mixed revisions</error>');
			$this->updateWorkingCopy($wc_path);

			return;
		}

		$working_copy_revision = $this->repositoryConnector->getLastRevision($wc_path);
		$repository_revision = $this->repositoryConnector->getLastRevision(
			$this->repositoryConnector->getWorkingCopyUrl($wc_path)
		);

		if ( $repository_revision > $working_copy_revision ) {
			$this->io->writeln('<error>Out of date</error>');
			$this->updateWorkingCopy($wc_path);

			return;
		}

		$this->io->writeln('<info>Up to date</info>');
	}

	/**
	 * Updates working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 * @throws CommandException When unable to perform an update.
	 */
	protected function updateWorkingCopy($wc_path)
	{
		$this->runOtherCommand('update', array('path' => $wc_path));
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
			$error_msg = 'Unable to determine source url for merge.' . PHP_EOL;
			$error_msg .= 'Please specify it manually using "--source-url" option';
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

			if ( $path == $source_path ) {
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

			foreach ( $revisions as $revision ) {
				$revision_log->getRevisionData('summary', $revision);
			}
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

		foreach ( $revisions as $revision ) {
			$command = $this->repositoryConnector->getCommand(
				'merge',
				'-c ' . $revision . ' {' . $source_url . '} {' . $wc_path . '}'
			);

			$merge_line = '--- Merging r' . $revision . " into '.':";
			$command->runLive(array(
				$wc_path => '.',
				$merge_line => '<fg=white;options=bold>' . $merge_line . '</>',
			));

			$this->_unmergedRevisions = array_diff($this->_unmergedRevisions, array($revision));
			$this->ensureWorkingCopyWithoutConflicts($source_url, $wc_path, $revision);
		}
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

		$conflicts = $this->repositoryConnector->getWorkingCopyConflicts($wc_path);

		if ( !$conflicts ) {
			$this->io->writeln('<info>Successful</info>');

			return;
		}

		$this->rememberConflicts($conflicts);
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

		foreach ( $conflicts as $conflict_path ) {
			$path_revisions = $revision_log->find('paths', $source_path . $conflict_path);
			$path_revisions = array_intersect($this->_unmergedRevisions, $path_revisions);

			if ( $path_revisions && isset($largest_suggested_revision) ) {
				$path_revisions = $this->limitRevisions($path_revisions, $largest_suggested_revision);
			}

			$table->addRow(array(
				$conflict_path,
				$path_revisions ? implode(', ', $path_revisions) : '-',
			));
		}

		$table->render();

		throw new CommandException('Working copy contains unresolved merge conflicts.');
	}

	/**
	 * Adds new conflicts to already remembered ones.
	 *
	 * @param array $conflicts Conflicts.
	 *
	 * @return void
	 */
	protected function rememberConflicts(array $conflicts)
	{
		$previous_conflicts = $this->getSetting(self::SETTING_MERGE_RECENT_CONFLICTS);
		$new_conflicts = array_unique(array_merge($previous_conflicts, $conflicts));

		$this->setSetting(self::SETTING_MERGE_RECENT_CONFLICTS, $new_conflicts);
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
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new StringConfigSetting(self::SETTING_MERGE_SOURCE_URL, ''),
			new ArrayConfigSetting(self::SETTING_MERGE_RECENT_CONFLICTS, array()),
		);
	}

}
