<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace aik099\SVNBuddy\Command;


use aik099\SVNBuddy\Config\AbstractConfigSetting;
use aik099\SVNBuddy\Config\IntegerConfigSetting;
use aik099\SVNBuddy\Config\RegExpsConfigSetting;
use aik099\SVNBuddy\Exception\CommandException;
use aik099\SVNBuddy\Helper\DateHelper;
use aik099\SVNBuddy\Repository\Parser\RevisionListParser;
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
				'details',
				'd',
				InputOption::VALUE_NONE,
				'Shows path affected in each revision'
			)
			->addOption(
				'merge-oracle',
				null,
				InputOption::VALUE_NONE,
				'Detects commits with possible merge conflicts'
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
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$bugs = $this->getList($this->io->getOption('bugs'));
		$revisions = $this->getList($this->io->getOption('revisions'));

		if ( $bugs && $revisions ) {
			throw new \RuntimeException('The "--bugs" and "--revisions" options are mutually exclusive.');
		}

		$wc_url = $this->getWorkingCopyUrl();

		$missing_revisions = array();
		$revision_log = $this->getRevisionLog($wc_url);
		$revisions_by_path = $revision_log->find('paths', $this->repositoryConnector->getPathFromUrl($wc_url));

		if ( $revisions ) {
			$revisions = $this->_revisionListParser->expandRanges($revisions);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions);
			$missing_revisions = array_diff($revisions, $revisions_by_path);
		}
		elseif ( $bugs ) {
			// Only show bug-related revisions on given path. The $missing_revisions is always empty.
			$revisions_from_bugs = $revision_log->find('bugs', $bugs);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions_from_bugs);
		}

		if ( $missing_revisions ) {
			throw new CommandException(
				'No information about ' . implode(', ', $missing_revisions) . ' revision(-s).'
			);
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

		$this->printRevisions($revisions_by_path_with_limit, $wc_url, (boolean)$this->io->getOption('details'));

		$revisions_by_path_count = count($revisions_by_path);
		$revisions_by_path_with_limit_count = count($revisions_by_path_with_limit);

		if ( $revisions_by_path_count > $revisions_by_path_with_limit_count ) {
			$revisions_left = $revisions_by_path_count - $revisions_by_path_with_limit_count;
			$this->io->writeln($revisions_left . ' revision(-s) not shown');
		}
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
	 * @param array   $revisions      Revisions.
	 * @param string  $repository_url Repository url.
	 * @param boolean $with_details   Print extended revision details (e.g. paths changed).
	 *
	 * @return void
	 */
	protected function printRevisions(array $revisions, $repository_url, $with_details = false)
	{
		$merge_oracle = $this->io->getOption('merge-oracle');

		if ( $merge_oracle ) {
			$merge_conflict_regexps = $this->getMergeConflictRegExps();
		}

		$table = new Table($this->io->getOutput());
		$headers = array('Revision', 'Author', 'Date', 'Bug-ID', 'Log Message');

		if ( $merge_oracle ) {
			$headers[] = 'M.O.';
		}

		$table->setHeaders($headers);

		/** @var DateHelper $date_helper */
		$date_helper = $this->getHelper('date');

		$prev_bugs = null;
		$last_color = 'yellow';
		$last_revision = end($revisions);
		$revision_log = $this->getRevisionLog($repository_url);
		$repository_path = $this->repositoryConnector->getPathFromUrl($repository_url) . '/';

		foreach ( $revisions as $revision ) {
			$revision_data = $revision_log->getRevisionData('summary', $revision);
			list($log_message,) = explode(PHP_EOL, $revision_data['msg']);
			$log_message = preg_replace('/^\[fixes:.*?\]/', "\xE2\x9C\x94", $log_message);

			if ( mb_strlen($log_message) > 70 ) {
				$log_message = mb_substr($log_message, 0, 70 - 3) . '...';
			}

			$new_bugs = implode(', ', $revision_log->getRevisionData('bugs', $revision));

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

			$revision_paths = $revision_log->getRevisionData('paths', $revision);

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

		$ret = str_replace($repository_path, '', $ret);

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
