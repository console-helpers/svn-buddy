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
use aik099\SVNBuddy\Helper\DateHelper;
use aik099\SVNBuddy\RepositoryConnector\RevisionListParser;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogCommand extends AbstractCommand
{

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
				'limit',
				null,
				InputOption::VALUE_REQUIRED,
				'Maximum number of log entries',
				10
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$bugs = $this->getList($input->getOption('bugs'));
		$revisions = $this->getList($input->getOption('revisions'));

		if ( $bugs && $revisions ) {
			throw new \RuntimeException('The "--bugs" and "--revisions" options are mutually exclusive.');
		}

		$wc_url = $this->getWorkingCopyUrl();

		$revision_log = $this->getRevisionLog($wc_url);
		$revisions_by_path = $revision_log->getRevisionsFromPath($this->repositoryConnector->getPathFromUrl($wc_url));

		if ( $revisions ) {
			$revisions = $this->_revisionListParser->expandRanges($revisions);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions);
		}
		elseif ( $bugs ) {
			$revisions_from_bugs = $this->getBugsRevisions($bugs, $wc_url);
			$revisions_by_path = array_intersect($revisions_by_path, $revisions_from_bugs);
		}

		if ( !$revisions_by_path ) {
			throw new CommandException('No matching revisions found.');
		}

		rsort($revisions_by_path, SORT_NUMERIC);

		if ( $bugs || $revisions ) {
			// Don't limit revisions, when provided explicitly by user.
			$revisions_by_path_with_limit = $revisions_by_path;
		}
		else {
			// Apply limit only, when no explicit bugs/revisions are set.
			$revisions_by_path_with_limit = array_slice($revisions_by_path, 0, $input->getOption('limit'));
		}

		$this->printRevisions($revisions_by_path_with_limit, $wc_url, (boolean)$input->getOption('details'));

		$revisions_by_path_count = count($revisions_by_path);
		$revisions_by_path_with_limit_count = count($revisions_by_path_with_limit);

		if ( $revisions_by_path_count > $revisions_by_path_with_limit_count ) {
			$revisions_left = $revisions_by_path_count - $revisions_by_path_with_limit_count;
			$output->writeln($revisions_left . ' revision(-s) not shown');
		}
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
		$table = new Table($this->output);
		$table->setHeaders(array('Revision', 'Author', 'Date', 'Bug-ID', 'Log Message'));

		/** @var DateHelper $date_helper */
		$date_helper = $this->getHelper('date');

		$prev_bugs = null;
		$last_color = 'yellow';
		$last_revision = end($revisions);
		$revision_log = $this->getRevisionLog($repository_url);
		$repository_path = $this->repositoryConnector->getPathFromUrl($repository_url) . '/';

		foreach ( $revisions as $revision ) {
			$revision_data = $revision_log->getRevisionData($revision);
			list($log_message,) = explode(PHP_EOL, $revision_data['msg']);
			$log_message = preg_replace('/^\[fixes:.*?\]/', "\xE2\x9C\x94", $log_message);

			if ( mb_strlen($log_message) > 70 ) {
				$log_message = mb_substr($log_message, 0, 70 - 3) . '...';
			}

			$new_bugs = implode(', ', $revision_data['bugs']);

			if ( isset($prev_bugs) && $new_bugs <> $prev_bugs ) {
				$last_color = $last_color == 'yellow' ? 'magenta' : 'yellow';
			}

			$table->addRow(array(
				$revision,
				$revision_data['author'],
				$date_helper->getAgoTime($revision_data['date']),
				'<fg=' . $last_color . '>' . $new_bugs . '</>',
				$log_message,
			));

			if ( $with_details ) {
				$details = '<fg=white;options=bold>Changed Paths:</>';

				foreach ( $revision_data['paths'] as $path_data ) {
					$path_action = $path_data['action'];
					$relative_path = $this->_getRelativeLogPath($path_data, 'path', $repository_path);

					$details .= PHP_EOL . ' * ';

					if ( $path_action == 'A' ) {
						$color = 'green';
					}
					elseif ( $path_action == 'D' ) {
						$color = 'red';
					}
					else {
						$color = '';
					}

					$to_colorize = $path_action . '    ' . $relative_path;

					if ( isset($path_data['copyfrom-path']) ) {
						$copy_from_rev = $path_data['copyfrom-rev'];
						$copy_from_path = $this->_getRelativeLogPath($path_data, 'copyfrom-path', $repository_path);
						$to_colorize .= PHP_EOL . '        (from ' . $copy_from_path . ':' . $copy_from_rev . ')';
					}

					if ( $color ) {
						$to_colorize = '<fg=' . $color . '>' . $to_colorize . '</>';
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

}
