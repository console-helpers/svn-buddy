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


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends AbstractCommand implements IAggregatorAwareCommand
{

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('cleanup')
			->setDescription(
				'Recursively clean up the working copy, removing locks, resuming unfinished operations, etc.'
			)
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

		$this->io->writeln('Cleaning up working copy ... ');
		$command = $this->repositoryConnector->getCommand('cleanup', array($wc_path));
		$command->runLive(array(
			$wc_path => '.',
		));
		$this->io->writeln('<info>Done</info>');
	}

	/**
	 * Returns option names, that makes sense to use in aggregation mode.
	 *
	 * @return array
	 */
	public function getAggregatedOptions()
	{
		return array();
	}

}
