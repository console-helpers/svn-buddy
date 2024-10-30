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
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReparseCommand extends AbstractCommand
{

	/**
	 * Revision log
	 *
	 * @var RevisionLog
	 */
	private $_revisionLog;

	/**
	 * Revision list parser.
	 *
	 * @var RevisionListParser
	 */
	private $_revisionListParser;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('reparse')
			->setDescription('Reparses given revision')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'revisions',
				'r',
				InputOption::VALUE_REQUIRED,
				'List of revision(-s) and/or revision range(-s) to reparse, e.g. <comment>53324</comment>, <comment>1224-4433</comment> or <comment>all</comment>'
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
	 * @throws CommandException When mandatory "revision" option wasn't given.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$revisions = $this->io->getOption('revisions');

		if ( !$revisions ) {
			throw new CommandException('The "revisions" option is mandatory.');
		}

		// FIXME: Not checking, that given revisions belong to a working copy.
		$revisions = $this->_revisionListParser->expandRanges($this->getList($revisions));

		foreach ( $this->_revisionListParser->collapseRanges($revisions) as $revision_range ) {
			if ( strpos($revision_range, '-') === false ) {
				$this->_revisionLog->reparse($revision_range, $revision_range);
			}
			else {
				list($from_revision, $to_revision) = explode('-', $revision_range, 2);
				$this->_revisionLog->reparse($from_revision, $to_revision);
			}
		}
	}

}
