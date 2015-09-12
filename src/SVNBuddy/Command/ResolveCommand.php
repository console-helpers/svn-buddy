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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResolveCommand extends AbstractCommand
{

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('resolve')
			->setDescription('Interactively resolves working copy conflicts')
			->setHelp($description)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$conflicts = $this->repositoryConnector->getWorkingCopyConflicts($wc_path);

		if ( !$conflicts ) {
			throw new CommandException('No conflicts detected.');
		}

		$resolve_path = $this->getResolvePath($conflicts);

		if ( is_dir($resolve_path) ) {
			throw new CommandException('Interactive tree conflict resolution is not supported');
		}

		$output->writeln('Resolving conflicts for: ' . $resolve_path);

		/*
		 * 1. copy conflicted file to temp dir
		 * 2. open interactive editor with conflicted file in temp dir
		 * 3. after editor exists ask if user is happy with merge result
		 * 4. if he is happy, then:
		 * - replace conflicted file with file from temp dir
		 * - run "svn resolve file_path --accept working"
		 * 5. if he isn't happy open editor again
		 * 6. repeat until all conflicts are resolved
		 *
		 *
		 *
		 */
	}

	/**
	 * Returns path to resolve.
	 *
	 * @param array $conflicts Conflicts.
	 *
	 * @return string
	 */
	protected function getResolvePath(array $conflicts)
	{
		return $this->io->choose(
			'Select path for to resolve conflicts for',
			$conflicts,
			0,
			'Path index %s is invalid.'
		);
	}

}
