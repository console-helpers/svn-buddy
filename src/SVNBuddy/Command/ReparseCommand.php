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
				'revision',
				'r',
				InputOption::VALUE_REQUIRED,
				'Reparse specified revision'
			);

		parent::configure();
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
		$revision = $this->io->getOption('revision');

		if ( !$revision ) {
			throw new CommandException('The "revision" option is mandatory.');
		}

		$this->_revisionLog->reparse($revision);
	}

}
