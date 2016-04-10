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
use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogCommand extends AbstractCommand implements IAggregatorAwareCommand, IConfigAwareCommand
{

	const SETTING_LOG_LIMIT = 'log.limit';

	const SETTING_LOG_MERGE_CONFLICT_REGEXPS = 'log.merge-conflict-regexps';

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
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{
		parent::prepareDependencies();

		$container = $this->getContainer();

		$this->_revisionListParser = $container['revision_list_parser'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this->pathAcceptsUrl = true;

		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('log')
			->setDescription(
				'Show the log messages for revisions/bugs/path'
			)
			->setHelp($description)
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
				'Revision or revision range (e.g. "53324,34342,1224-4433,232")'
			)
			->addOption(
				'bugs',
				'b',
				InputOption::VALUE_REQUIRED,
				'Bugs to merge (e.g. "JRA-1234,43644")'
			)
			->addOption(
				'refs',
				null,
				InputOption::VALUE_REQUIRED,
				'Refs (e.g. "trunk", "branches/branch-name", "tags/tag-name")'
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
			->addOption(
				'merge-oracle',
				null,
				InputOption::VALUE_NONE,
				'Detects commits with possible merge conflicts'
			)
			->addOption(
				'merges',
				null,
				InputOption::VALUE_NONE,
				'Print only merge commits'
			)
			->addOption(
				'no-merges',
				null,
				InputOption::VALUE_NONE,
				'Do not print merge commits'
			)
			->addOption(
				'merged',
				null,
				InputOption::VALUE_NONE,
				'Print only merged commits'
			)
			->addOption(
				'merged-by',
				null,
				InputOption::VALUE_REQUIRED,
				'Show revisions merged via given revision(-s)'
			)
			->addOption(
				'not-merged',
				null,
				InputOption::VALUE_NONE,
				'Print only not merged commits'
			)
			->addOption(
				'merge-status',
				null,
				InputOption::VALUE_NONE,
				'Show merge revisions (if any) for each revisions'
			)
			->addOption(
				'limit',
				null,
				InputOption::VALUE_REQUIRED,
				'Maximum number of log entries'
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
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
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
			// Exclude revisions, that were merged outside of project root folder in repository.
			$merged_by = $this->_revisionListParser->expandRanges($merged_by);
			$revisions_by_path = $this->_revisionLog->find(
				'paths',
				$this->repositoryConnector->getProjectUrl(
					$this->repositoryConnector->getRelativePath($this->getWorkingCopyPath())
				)
			);
			$revisions_by_path = array_intersect($revisions_by_path, $this->_revisionLog->find('merges', $merged_by));
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
			$revisions_by_path_with_limit = array_slice($revisions_by_path, 0, $this->getLimit());
		}

		$revisions_by_path_count = count($revisions_by_path);
		$revisions_by_path_with_limit_count = count($revisions_by_path_with_limit);

		$this->io->writeln(sprintf(
			' * Showing <info>%d</info> of <info>%d</info> revision(-s):',
			$revisions_by_path_with_limit_count,
			$revisions_by_path_count
		));

		$this->printRevisions($revisions_by_path_with_limit, (boolean)$this->io->getOption('details'));
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
	 * @throws CommandException When given refs doesn't exist.
	 */
	protected function getRevisionsByPath()
	{
		$refs = $this->getList($this->io->getOption('refs'));
		$relative_path = $this->repositoryConnector->getRelativePath($this->getWorkingCopyPath());

		if ( !$refs ) {
			$ref = $this->repositoryConnector->getRefByPath($relative_path);

			// Use search by ref, when working copy represents ref root folder.
			if ( $ref !== false && preg_match('/' . preg_quote($ref, '/') . '$/', $relative_path) ) {
				return $this->_revisionLog->find('refs', $ref);
			}
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
	 * Returns displayed revision limit.
	 *
	 * @return integer
	 */
	protected function getLimit()
	{
		$option_limit = $this->io->getOption('limit');

		if ( $option_limit !== null ) {
			return $option_limit;
		}

		return $this->getSetting(self::SETTING_LOG_LIMIT);
	}

	/**
	 * Prints revisions.
	 *
	 * @param array   $revisions    Revisions.
	 * @param boolean $with_details Print extended revision details (e.g. paths changed).
	 *
	 * @return void
	 */
	protected function printRevisions(array $revisions, $with_details = false)
	{
		$table = new Table($this->io->getOutput());
		$headers = array('Revision', 'Author', 'Date', 'Bug-ID', 'Log Message');

		// Add "Summary" header.
		if ( $this->io->getOption('summary') ) {
			$headers[] = 'Summary';
		}

		$merge_oracle = $this->io->getOption('merge-oracle');

		// Add "M.O." header.
		if ( $merge_oracle ) {
			$headers[] = 'M.O.';
			$merge_conflict_regexps = $this->getMergeConflictRegExps();
		}

		// Add "Merged Via" header.
		$merge_status = $this->io->getOption('merge-status');

		if ( $merge_status ) {
			$headers[] = 'Merged Via';
		}

		$table->setHeaders($headers);

		/** @var DateHelper $date_helper */
		$date_helper = $this->getHelper('date');

		$prev_bugs = null;
		$last_color = 'yellow';
		$last_revision = end($revisions);

		$repository_path = $this->repositoryConnector->getRelativePath(
			$this->getWorkingCopyPath()
		) . '/';

		foreach ( $revisions as $revision ) {
			$revision_data = $this->_revisionLog->getRevisionData('summary', $revision);
			list($log_message,) = explode(PHP_EOL, $revision_data['msg']);
			$log_message = preg_replace('/^\[fixes:.*?\]/', "\xE2\x9C\x94", $log_message);

			if ( mb_strlen($log_message) > 68 ) {
				$log_message = mb_substr($log_message, 0, 68 - 3) . '...';
			}

			$new_bugs = implode(', ', $this->_revisionLog->getRevisionData('bugs', $revision));

			if ( isset($prev_bugs) && $new_bugs <> $prev_bugs ) {
				$last_color = $last_color == 'yellow' ? 'magenta' : 'yellow';
			}

			$row = array(
				$revision,
				$revision_data['author'],
				$date_helper->getAgoTime($revision_data['date']),
				'<fg=' . $last_color . '>' . $new_bugs . '</>',
				$log_message,
			);

			$revision_paths = $this->_revisionLog->getRevisionData('paths', $revision);

			// Add "Summary" column.
			if ( $this->io->getOption('summary') ) {
				$row[] = $this->generateChangeSummary($revision_paths);
			}

			// Add "M.O." column.
			if ( $merge_oracle ) {
				$merge_conflict_predication = $this->getMergeConflictPrediction(
					$revision_paths,
					$merge_conflict_regexps
				);
				$row[] = $merge_conflict_predication ? '<error>' . count($merge_conflict_predication) . '</error>' : '';
			}
			else {
				$merge_conflict_predication = array();
			}

			// Add "Merged Via" column.
			if ( $merge_status ) {
				$merged_via = $this->_revisionLog->getRevisionData('merges', $revision);
				$row[] = $merged_via ? implode(', ', $merged_via) : '';
			}

			$table->addRow($row);

			if ( $with_details ) {
				$details = '<fg=white;options=bold>Changed Paths:</>';

				foreach ( $revision_paths as $path_data ) {
					$path_action = $path_data['action'];
					$relative_path = $this->_getRelativeLogPath($path_data, 'path', $repository_path);

					$details .= PHP_EOL . ' * ';

					if ( $path_action == 'A' ) {
						$color_format = 'fg=green';
					}
					elseif ( $path_action == 'D' ) {
						$color_format = 'fg=red';
					}
					else {
						$color_format = in_array($path_data['path'], $merge_conflict_predication) ? 'error' : '';
					}

					$to_colorize = $path_action . '    ' . $relative_path;

					if ( isset($path_data['copyfrom-path']) ) {
						$copy_from_rev = $path_data['copyfrom-rev'];
						$copy_from_path = $this->_getRelativeLogPath($path_data, 'copyfrom-path', $repository_path);
						$to_colorize .= PHP_EOL . '        (from ' . $copy_from_path . ':' . $copy_from_rev . ')';
					}

					if ( $color_format ) {
						$to_colorize = '<' . $color_format . '>' . $to_colorize . '</>';
					}

					$details .= $to_colorize;
				}

				$table->addRow(new TableSeparator());
				$table->addRow(array(new TableCell($details, array('colspan' => 5))));

				if ( $revision != $last_revision ) {
					$table->addRow(new TableSeparator());
				}
			}

			$prev_bugs = $new_bugs;
		}

		$table->render();
	}

	/**
	 * Generates change summary for a revision.
	 *
	 * @param array $revision_paths Revision paths.
	 *
	 * @return string
	 */
	protected function generateChangeSummary(array $revision_paths)
	{
		$summary = array('added' => 0, 'changed' => 0, 'removed' => 0);

		foreach ( $revision_paths as $path_data ) {
			$path_action = $path_data['action'];

			if ( $path_action == 'A' ) {
				$summary['added']++;
			}
			elseif ( $path_action == 'D' ) {
				$summary['removed']++;
			}
			else {
				$summary['changed']++;
			}
		}

		if ( $summary['added'] ) {
			$summary['added'] = '<fg=green>+' . $summary['added'] . '</>';
		}

		if ( $summary['removed'] ) {
			$summary['removed'] = '<fg=red>-' . $summary['removed'] . '</>';
		}

		return implode(' ', array_filter($summary));
	}

	/**
	 * Returns merge conflict path predictions.
	 *
	 * @param array $revision_paths         Revision paths.
	 * @param array $merge_conflict_regexps Merge conflict paths.
	 *
	 * @return array
	 */
	protected function getMergeConflictPrediction(array $revision_paths, array $merge_conflict_regexps)
	{
		if ( !$merge_conflict_regexps ) {
			return array();
		}

		$conflict_paths = array();

		foreach ( $revision_paths as $revision_path ) {
			foreach ( $merge_conflict_regexps as $merge_conflict_regexp ) {
				if ( preg_match($merge_conflict_regexp, $revision_path['path']) ) {
					$conflict_paths[] = $revision_path['path'];
				}
			}
		}

		return $conflict_paths;
	}

	/**
	 * Returns merge conflict regexps.
	 *
	 * @return array
	 */
	protected function getMergeConflictRegExps()
	{
		return $this->getSetting(self::SETTING_LOG_MERGE_CONFLICT_REGEXPS);
	}

	/**
	 * Returns relative path to "svn log" returned path.
	 *
	 * @param array  $path_data       Path data.
	 * @param string $path_key        Path key.
	 * @param string $repository_path Repository path.
	 *
	 * @return string
	 */
	private function _getRelativeLogPath(array $path_data, $path_key, $repository_path)
	{
		$ret = $path_data[$path_key];

		if ( $path_data['kind'] == 'dir' ) {
			$ret .= '/';
		}

		$ret = preg_replace('/^' . preg_quote($repository_path, '/') . '/', '', $ret, 1);

		if ( $ret === '' ) {
			$ret = '.';
		}

		return $ret;
	}

	/**
	 * Returns URL to the working copy.
	 *
	 * @return string
	 */
	protected function getWorkingCopyUrl()
	{
		$wc_path = $this->getWorkingCopyPath();

		if ( !$this->repositoryConnector->isUrl($wc_path) ) {
			return $this->repositoryConnector->getWorkingCopyUrl($wc_path);
		}

		return $wc_path;
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
			new RegExpsConfigSetting(self::SETTING_LOG_MERGE_CONFLICT_REGEXPS, '#/composer\.lock$#'),
		);
	}

}
