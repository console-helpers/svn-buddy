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


use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\Plugin\DatabaseCollectorPlugin\ProjectsPlugin;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLog;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends AbstractCommand implements IAggregatorAwareCommand
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
			->setName('info')
			->setDescription('Displays working copy information')
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
		$wc_path = $this->getWorkingCopyPath();

		$command = $this->repositoryConnector->getCommand('info', array($wc_path, '--xml'));
		$xml = $command->run();

		$table_data = array(
			array('Path', $xml->entry['path']),
			array('Working Copy Root Path', $xml->entry->{'wc-info'}->{'wcroot-abspath'}),
			array('URL', $xml->entry->url),
			array('Relative URL', $xml->entry->{'relative-url'}),
			array('Repository Root', '<info>' . $xml->entry->repository->root . '</info>'),
			array('Repository UUID', $xml->entry->repository->uuid),
			array('Revision', $xml->entry['revision']),
			array('Node Kind', $xml->entry['kind']), // TODO: Prettify "dir = directory, ...".
			array('Schedule', $xml->entry->{'wc-info'}->schedule),
			array('Last Changed Author', $xml->entry->commit->author),
			array('Last Changed Rev', $xml->entry->commit['revision']),
			array('Last Changed Date', $xml->entry->commit->date),
		);

		$table = new Table($this->io->getOutput());
		$table->setHeaders(array('Field Name', 'Field Value'));
		$table->setRows($table_data);
		$table->render();
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
