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


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevertCommand extends AbstractCommand implements IAggregatorAwareCommand
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
			->setName('revert')
			->setDescription('Restore pristine working copy file (undo most local edits)')
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

		$output->writeln('Reverting local changes in working copy ... ');
		$command = $this->repositoryConnector->getCommand(
			'revert',
			'--depth infinity {' . $wc_path . '}'
		);
		$command->runLive(array(
			$wc_path => '.',
		));
		$output->writeln('<info>Done</info>');
	}

}
