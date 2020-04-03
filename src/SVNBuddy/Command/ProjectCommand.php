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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\BugsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\ProjectsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectCommand extends AbstractCommand
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
			->setName('project')
			->setDescription('Changes and displays project configuration')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'refresh-bug-tracking',
				null,
				InputOption::VALUE_NONE,
				'Refreshes value of "bugtraq:logregex" SVN property of the project'
			)
			->addOption(
				'show-meta',
				null,
				InputOption::VALUE_NONE,
				'Shows meta information of a project'
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
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$project_path = $this->_revisionLog->getProjectPath();
		$refresh_bug_tracking = $this->io->getOption('refresh-bug-tracking');
		$show_meta = $this->io->getOption('show-meta');

		if ( !$refresh_bug_tracking && !$show_meta ) {
			$this->io->writeln('Path in repository: <info>' . $project_path . '</info>');

			return;
		}

		if ( $refresh_bug_tracking ) {
			/** @var BugsPlugin $bugs_plugin */
			$bugs_plugin = $this->_revisionLog->getPlugin('bugs');
			$bugs_plugin->refreshBugRegExp($project_path);

			$this->io->writeln('The "<info>' . $project_path . '</info>" project bug tracking expression was reset.');
		}
		elseif ( $show_meta ) {
			$this->io->writeln(
				'Showing project meta information for <info>' . $this->getWorkingCopyUrl() . '</info> url:'
			);

			$table = new Table($this->io->getOutput());

			$table->setHeaders(array('Field Name', 'Field Value'));

			/** @var ProjectsPlugin $projects_plugin */
			$projects_plugin = $this->_revisionLog->getPlugin('projects');

			foreach ( $projects_plugin->getMeta($project_path) as $field_name => $field_value ) {
				$table->addRow(array($field_name, $field_value));
			}

			$table->render();
		}
	}

}
