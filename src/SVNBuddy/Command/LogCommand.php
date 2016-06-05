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
use ConsoleHelpers\SVNBuddy\Config\IntegerConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\RegExpsConfigSetting;
use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionPrinter;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogCommand extends AbstractCommand implements IAggregatorAwareCommand, IConfigAwareCommand
{

	const SETTING_LOG_LIMIT = 'log.limit';

	const SETTING_LOG_MESSAGE_LIMIT = 'log.message-limit';

	const SETTING_LOG_MERGE_CONFLICT_REGEXPS = 'log.merge-conflict-regexps';

	const ALL_REFS = 'all';

	/**
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

	/**
	 * Revision log
	 *
	 * @var RevisionLog
	 */
	private $_revisionLog;

	/**
	 * Revision printer.
	 *
	 * @var RevisionPrinter
	 */
	private $_revisionPrinter;

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
		$this->_revisionPrinter = $container['revision_printer'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this->pathAcceptsUrl = true;

		$this
			->setName('log')
			->setDescription(
				'Show the log messages for a set of revisions, bugs, paths, refs, etc.'
			)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path or URL',
				'.'
			)
			->addOption(
				'revisions',
				'r',
				InputOption::VALUE_REQUIRED,
				'List of revision(-s) and/or revision range(-s), e.g. <comment>53324</comment>, <comment>1224-4433</comment>'
			)
			->addOption(
				'bugs',
				'b',
				InputOption::VALUE_REQUIRED,
				'List of bug(-s), e.g. <comment>JRA-1234</comment>, <comment>43644</comment>'
			)
			->addOption(
				'refs',
				null,
				InputOption::VALUE_REQUIRED,
				'List of refs, e.g. <comment>trunk</comment>, <comment>branches/branch-name</comment>, <comment>tags/tag-name</comment> or <comment>all</comment> for all refs'
			)
			->addOption(
				'merges',
				null,
				InputOption::VALUE_NONE,
				'Show merge revisions only'
			)
			->addOption(
				'no-merges',
				null,
				InputOption::VALUE_NONE,
				'Hide merge revisions'
			)
			->addOption(
				'merged',
				null,
				InputOption::VALUE_NONE,
				'Shows only revisions, that were merged at least once'
			)
			->addOption(
				'not-merged',
				null,
				InputOption::VALUE_NONE,
				'Shows only revisions, that were not merged'
			)
			->addOption(
				'merged-by',
				null,
				InputOption::VALUE_REQUIRED,
				'Show revisions merged by list of revision(-s) and/or revision range(-s)'
			)
			->addOption(
				'action',
				null,
				InputOption::VALUE_REQUIRED,
				'Show revisions, whose paths were affected by specified action, e.g. <comment>A</comment>, <comment>M</comment>, <comment>R</comment>, <comment>D</comment>'
			)
			->addOption(
				'kind',
				null,
				InputOption::VALUE_REQUIRED,
				'Show revisions, whose paths match specified kind, e.g. <comment>dir</comment> or <comment>file</comment>'
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
				'with-refs',
				null,
				InputOption::VALUE_NONE,
				'Shows revision refs'
			)
			->addOption(
				'with-merge-oracle',
				null,
				InputOption::VALUE_NONE,
				'Shows number of paths in the revision, that can cause conflict upon merging'
			)
			->addOption(
				'with-merge-status',
				null,
				InputOption::VALUE_NONE,
				'Shows merge revisions affecting this revision'
			)
			->addOption(
				'max-count',
				null,
				InputOption::VALUE_REQUIRED,
				'Limit the number of revisions to output'
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

		if ( $optionName === 'refs' ) {
			return $this->getAllRefs();
		}
		elseif ( $optionName === 'action' ) {
			return $this->getAllActions();
		}
		elseif ( $optionName === 'kind' ) {
			return $this->getAllKinds();
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	public function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		$this->_revisionLog = $this->getRevisionLog($this->getWorkingCopyUrl());
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When both "--bugs" and "--revisions" options were specified.
	 * @throws CommandException When specified revisions are not present in current project.
	 * @throws CommandException When project contains no associated revisions.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$searching_start = microtime(true);
		$bugs = $this->getList($this->io->getOption('bugs'));
		$revisions = $this->getList($this->io->getOption('revisions'));

		if ( $bugs && $revisions ) {
			throw new \RuntimeException('The "--bugs" and "--revisions" options are mutually exclusive.');
		}

		$missing_revisions = array();
		$revisions_by_path = $this->getRevisionsByPath();

		if ( $revisions ) {
			$revisions = $this->_revisionListParser->expandRanges($revisions);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions);
			$missing_revisions = array_diff($revisions, $revisions_by_path);
		}
		elseif ( $bugs ) {
			// Only show bug-related revisions on given path. The $missing_revisions is always empty.
			$revisions_from_bugs = $this->_revisionLog->find('bugs', $bugs);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions_from_bugs);
		}

		$merged_by = $this->getList($this->io->getOption('merged-by'));

		if ( $merged_by ) {
			$merged_by = $this->_revisionListParser->expandRanges($merged_by);
			$revisions_by_path = $this->_revisionLog->find('merges', $merged_by);
		}

		if ( $this->io->getOption('merges') ) {
			$revisions_by_path = array_intersect($revisions_by_path, $this->_revisionLog->find('merges', 'all_merges'));
		}
		elseif ( $this->io->getOption('no-merges') ) {
			$revisions_by_path = array_diff($revisions_by_path, $this->_revisionLog->find('merges', 'all_merges'));
		}

		if ( $this->io->getOption('merged') ) {
			$revisions_by_path = array_intersect($revisions_by_path, $this->_revisionLog->find('merges', 'all_merged'));
		}
		elseif ( $this->io->getOption('not-merged') ) {
			$revisions_by_path = array_diff($revisions_by_path, $this->_revisionLog->find('merges', 'all_merged'));
		}

		$action = $this->io->getOption('action');

		if ( $action ) {
			if ( !in_array($action, $this->getAllActions()) ) {
				throw new CommandException('The "' . $action . '" action is unknown.');
			}

			$revisions_by_path = array_intersect(
				$revisions_by_path,
				$this->_revisionLog->find('paths', 'action:' . $action)
			);
		}

		$kind = $this->io->getOption('kind');

		if ( $kind ) {
			if ( !in_array($kind, $this->getAllKinds()) ) {
				throw new CommandException('The "' . $kind . '" kind is unknown.');
			}

			$revisions_by_path = array_intersect(
				$revisions_by_path,
				$this->_revisionLog->find('paths', 'kind:' . $kind)
			);
		}

		if ( $missing_revisions ) {
			throw new CommandException($this->getMissingRevisionsErrorMessage($missing_revisions));
		}
		elseif ( !$revisions_by_path ) {
			throw new CommandException('No matching revisions found.');
		}

		rsort($revisions_by_path, SORT_NUMERIC);

		if ( $bugs || $revisions ) {
			// Don't limit revisions, when provided explicitly by user.
			$revisions_by_path_with_limit = $revisions_by_path;
		}
		else {
			// Apply limit only, when no explicit bugs/revisions are set.
			$revisions_by_path_with_limit = array_slice($revisions_by_path, 0, $this->getMaxCount());
		}

		$revisions_by_path_count = count($revisions_by_path);
		$revisions_by_path_with_limit_count = count($revisions_by_path_with_limit);

		if ( $revisions_by_path_with_limit_count === $revisions_by_path_count ) {
			$this->io->writeln(sprintf(
				' * Showing <info>%d</info> revision(-s) in %s:',
				$revisions_by_path_with_limit_count,
				$this->getRevisionLogIdentifier()
			));
		}
		else {
			$this->io->writeln(sprintf(
				' * Showing <info>%d</info> of <info>%d</info> revision(-s) in %s:',
				$revisions_by_path_with_limit_count,
				$revisions_by_path_count,
				$this->getRevisionLogIdentifier()
			));
		}

		$searching_duration = microtime(true) - $searching_start;

		$printing_start = microtime(true);
		$this->printRevisions($revisions_by_path_with_limit);
		$printing_duration = microtime(true) - $printing_start;

		$debug_info = 'Generation Time: <info>' . round($searching_duration + $printing_duration, 2) . 's</info>';
		$debug_info .= ' (';
		$debug_info .= 'searching: <info>' . round($searching_duration, 2) . 's</info>;';
		$debug_info .= ' printing: <info>' . round($printing_duration, 2) . 's</info>';
		$debug_info .= ').';

		$this->io->writeln(
			$debug_info
		);
	}

	/**
	 * Returns all refs.
	 *
	 * @return array
	 */
	protected function getAllRefs()
	{
		$ret = parent::getAllRefs();
		$ret[] = self::ALL_REFS;

		return $ret;
	}

	/**
	 * Returns all actions.
	 *
	 * @return array
	 */
	protected function getAllActions()
	{
		return array('A', 'M', 'R', 'D');
	}

	/**
	 * Returns all actions.
	 *
	 * @return array
	 */
	protected function getAllKinds()
	{
		return array('dir', 'file');
	}

	/**
	 * Returns revision log identifier.
	 *
	 * @return string
	 */
	protected function getRevisionLogIdentifier()
	{
		$ret = '<info>' . $this->_revisionLog->getProjectPath() . '</info> project';

		$ref_name = $this->_revisionLog->getRefName();

		if ( $ref_name ) {
			$ret .= ' (ref: <info>' . $ref_name . '</info>)';
		}
		else {
			$ret .= ' (all refs)';
		}

		return $ret;
	}

	/**
	 * Shows error about missing revisions.
	 *
	 * @param array $missing_revisions Missing revisions.
	 *
	 * @return string
	 */
	protected function getMissingRevisionsErrorMessage(array $missing_revisions)
	{
		$refs = $this->io->getOption('refs');
		$missing_revisions = implode(', ', $missing_revisions);

		if ( $refs ) {
			$revision_source = 'in "' . $refs . '" ref(-s)';
		}
		else {
			$revision_source = 'at "' . $this->getWorkingCopyUrl() . '" url';
		}

		return 'The ' . $missing_revisions . ' revision(-s) not found ' . $revision_source . '.';
	}

	/**
	 * Returns list of revisions by path.
	 *
	 * @return array
	 * @throws CommandException When given path doesn't exist.
	 * @throws CommandException When given refs doesn't exist.
	 */
	protected function getRevisionsByPath()
	{
		// When "$path" points to deleted path the "$wc_path" will be parent folder of it (hopefully existing folder).
		$path = $this->io->getArgument('path');
		$wc_path = $this->getWorkingCopyPath();

		$refs = $this->getList($this->io->getOption('refs'));
		$relative_path = $this->repositoryConnector->getRelativePath($wc_path);

		if ( !$this->repositoryConnector->isUrl($wc_path) ) {
			$relative_path .= $this->_getPathDifference($wc_path, $path);
		}

		$relative_path = $this->_crossReferencePathFromRepository($relative_path);

		if ( $relative_path === null ) {
			throw new CommandException(
				'The "' . $path . '" path not found in "' . $this->_revisionLog->getProjectPath() . '" project.'
			);
		}

		if ( $refs ) {
			$incorrect_refs = array_diff($refs, $this->getAllRefs());

			if ( $incorrect_refs ) {
				throw new CommandException(
					'The following refs are unknown: "' . implode('", "', $incorrect_refs) . '".'
				);
			}

			return $this->_revisionLog->find('refs', $refs);
		}

		return $this->_revisionLog->find('paths', $relative_path);
	}

	/**
	 * Returns difference between 2 paths.
	 *
	 * @param string $main_path Main path.
	 * @param string $sub_path  Sub path.
	 *
	 * @return string
	 */
	private function _getPathDifference($main_path, $sub_path)
	{
		if ( $sub_path === '.' || strpos($sub_path, '../') !== false ) {
			$sub_path = realpath($sub_path);
		}

		$adapted_sub_path = $sub_path;

		do {
			$sub_path_pos = strpos($main_path, $adapted_sub_path);

			if ( $sub_path_pos !== false ) {
				break;
			}

			$adapted_sub_path = dirname($adapted_sub_path);
		} while ( $adapted_sub_path !== '.' );

		// No sub-matches.
		if ( !strlen($adapted_sub_path) ) {
			return '';
		}

		return str_replace($adapted_sub_path, '', $sub_path);
	}

	/**
	 * Determines path kind from repository.
	 *
	 * @param string $path Path.
	 *
	 * @return string|null
	 */
	private function _crossReferencePathFromRepository($path)
	{
		$path = rtrim($path, '/');
		$try_paths = array($path, $path . '/');

		foreach ( $try_paths as $try_path ) {
			if ( $this->_revisionLog->find('paths', 'exact:' . $try_path) ) {
				return $try_path;
			}
		}

		return null;
	}

	/**
	 * Returns displayed revision limit.
	 *
	 * @return integer
	 */
	protected function getMaxCount()
	{
		$max_count = $this->io->getOption('max-count');

		if ( $max_count !== null ) {
			return $max_count;
		}

		return $this->getSetting(self::SETTING_LOG_LIMIT);
	}

	/**
	 * Prints revisions.
	 *
	 * @param array $revisions Revisions.
	 *
	 * @return void
	 */
	protected function printRevisions(array $revisions)
	{
		$column_mapping = array(
			'with-details' => RevisionPrinter::COLUMN_DETAILS,
			'with-summary' => RevisionPrinter::COLUMN_SUMMARY,
			'with-refs' => RevisionPrinter::COLUMN_REFS,
			'with-merge-oracle' => RevisionPrinter::COLUMN_MERGE_ORACLE,
			'with-merge-status' => RevisionPrinter::COLUMN_MERGE_STATUS,
		);

		foreach ( $column_mapping as $option_name => $column ) {
			if ( $this->io->getOption($option_name) ) {
				$this->_revisionPrinter->withColumn($column);
			}
		}

		$this->_revisionPrinter->setMergeConflictRegExps($this->getSetting(self::SETTING_LOG_MERGE_CONFLICT_REGEXPS));
		$this->_revisionPrinter->setLogMessageLimit($this->getSetting(self::SETTING_LOG_MESSAGE_LIMIT));

		$wc_path = $this->getWorkingCopyPath();

		if ( !$this->repositoryConnector->isUrl($wc_path) ) {
			$this->_revisionPrinter->setCurrentRevision(
				$this->repositoryConnector->getLastRevision($wc_path)
			);
		}

		$this->_revisionPrinter->printRevisions($this->_revisionLog, $revisions, $this->io->getOutput());
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		return array(
			new IntegerConfigSetting(self::SETTING_LOG_LIMIT, 10),
			new IntegerConfigSetting(self::SETTING_LOG_MESSAGE_LIMIT, 68),
			new RegExpsConfigSetting(self::SETTING_LOG_MERGE_CONFLICT_REGEXPS, '#/composer\.lock$#'),
		);
	}

}
