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
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchCommand extends AbstractCommand
{

	const MATCH_TYPE_FIRST = 'first';

	const MATCH_TYPE_LAST = 'last';

	/**
	 * Revision log
	 *
	 * @var RevisionLog
	 */
	private $_revisionLog;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('search')
			->setDescription('Searches for a revision, where text was added to a file or removed from it')
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'File path'
			)
			->addArgument(
				'keywords',
				InputArgument::REQUIRED,
				'Search keyword'
			)
			->addOption(
				'match-type',
				't',
				InputOption::VALUE_REQUIRED,
				'Match type, e.g. <comment>first</comment> or <comment>last</comment>',
				self::MATCH_TYPE_LAST
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

		if ( $optionName === 'match-type' ) {
			return $this->getMatchTypes();
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
	 * @throws \RuntimeException When invalid direction is specified.
	 * @throws \RuntimeException When keywords are empty.
	 * @throws CommandException When no revisions found for path specified.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$match_type = $this->io->getOption('match-type');

		if ( !in_array($match_type, $this->getMatchTypes()) ) {
			throw new \RuntimeException('The "' . $match_type . '" match type is invalid.');
		}

		$wc_path = $this->getWorkingCopyPath();
		$relative_path = $this->repositoryConnector->getRelativePath($wc_path);

		$keywords = $this->io->getArgument('keywords');

		if ( !strlen($keywords) ) {
			throw new \RuntimeException('The "keywords" are empty.');
		}

		$this->io->writeln(sprintf(
			'Searching for %s match of "<info>%s</info>" in "<info>%s</info>":',
			$match_type,
			$keywords,
			'.../' . basename($relative_path)
		));

		$revisions = $this->_revisionLog->find('paths', $relative_path);

		if ( !$revisions ) {
			throw new CommandException('No revisions found for "' . $relative_path . '" path.');
		}

		if ( $match_type === self::MATCH_TYPE_LAST ) {
			$revisions = array_reverse($revisions);
		}

		$scanned_revisions = 0;
		$total_revisions = count($revisions);

		$progress_bar = $this->io->createProgressBar($total_revisions);
		$progress_bar->setFormat(
			'<info>%message:6s%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%'
		);

		$index = 0;
		$found_revision = false;

		foreach ( $revisions as $index => $revision ) {
			$scanned_revisions++;
			$progress_bar->setMessage('r' . $revision);
			$progress_bar->display();

			$file_content = $this->repositoryConnector->getFileContent($wc_path, $revision);

			if ( strpos($file_content, $keywords) !== false ) {
				$found_revision = $revision;
				break;
			}

			$progress_bar->advance();
		}

		$this->io->writeln('');

		$scanned_percent = round(($scanned_revisions / $total_revisions) * 100);
		$this->io->writeln('Search efficiency: ' . $this->getSearchEfficiency(100 - $scanned_percent));

		if ( $found_revision ) {
			if ( $match_type === self::MATCH_TYPE_LAST ) {
				$this->io->writeln('Last seen at ' . $this->getRevisionInfo($found_revision) . '.');

				if ( array_key_exists($index - 1, $revisions) ) {
					$this->io->writeln('Deleted at ' . $this->getRevisionInfo($revisions[$index - 1]) . '.');
				}
			}
			else {
				$this->io->writeln('First seen at ' . $this->getRevisionInfo($found_revision) . '.');
			}

			return;
		}

		$this->io->writeln('<error>Keywords not found.</error>');
	}

	/**
	 * Returns match types.
	 *
	 * @return array
	 */
	protected function getMatchTypes()
	{
		return array(self::MATCH_TYPE_FIRST, self::MATCH_TYPE_LAST);
	}

	/**
	 * Returns search efficiency.
	 *
	 * @param float $percent Percent.
	 *
	 * @return string
	 */
	protected function getSearchEfficiency($percent)
	{
		if ( $percent >= 0 && $percent <= 20 ) {
			return '<fg=red>Very Poor</>';
		}

		if ( $percent > 20 && $percent <= 40 ) {
			return '<fg=red;options=bold>Poor</>';
		}

		if ( $percent > 40 && $percent <= 60 ) {
			return '<fg=yellow>Fair</>';
		}

		if ( $percent > 60 && $percent <= 80 ) {
			return '<fg=green;options=bold>Good</>';
		}

		if ( $percent > 80 && $percent <= 100 ) {
			return '<fg=green>Excellent</>';
		}

		return '';
	}

	/**
	 * Get revision information.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return string
	 */
	protected function getRevisionInfo($revision)
	{
		$bugs = $this->_revisionLog->getRevisionsData('bugs', array($revision));

		$ret = '<info>' . $revision . '</info>';

		if ( $bugs[$revision] ) {
			$ret .= ' (bugs: <info>' . implode('</info>, <info>', $bugs[$revision]) . '</info>)';
		}

		return $ret;
	}

}
