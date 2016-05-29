<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Command\Dev;


use ConsoleHelpers\SVNBuddy\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class PharCreateCommand extends AbstractCommand
{

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('dev:phar-create')
			->setDescription(
				'Creates PHAR for new release'
			)
			->addOption(
				'build-dir',
				null,
				InputOption::VALUE_REQUIRED,
				'Directory, where build results would be stored',
				'build'
			);

		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$build_dir = realpath($this->io->getOption('build-dir'));
		$repository_path = realpath(__DIR__ . '/../../../../');

		$box_config = json_decode(file_get_contents($repository_path . '/box.json.dist'), true);

		$phar_file = $build_dir . '/' . basename($box_config['output']);
		$signature_file = $phar_file . '.sig';

		$box_config['output'] = $phar_file;

		file_put_contents(
			$repository_path . '/box.json',
			json_encode($box_config, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0)
		);

		$box_cli = trim($this->_shellCommand('which', array('box')));
		$this->_shellCommand('php', array('-d', 'phar.readonly=0', $box_cli, 'build'), $repository_path);

		file_put_contents(
			$signature_file,
			$this->_shellCommand('sha1sum', array(basename($phar_file)), dirname($phar_file))
		);

		$this->io->writeln('Phar created successfully.');
	}

	/**
	 * Runs command.
	 *
	 * @param string      $command           Command.
	 * @param array       $arguments         Arguments.
	 * @param string|null $working_directory Working directory.
	 *
	 * @return string
	 */
	private function _shellCommand($command, array $arguments = array(), $working_directory = null)
	{
		$final_arguments = array_merge(array($command), $arguments);

		$process = ProcessBuilder::create($final_arguments)
			->setWorkingDirectory($working_directory)
			->getProcess();

		return $process->mustRun()->getOutput();
	}

}
