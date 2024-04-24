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


use ConsoleHelpers\ConsoleKit\Exception\CommandException;
use ConsoleHelpers\SVNBuddy\Command\AbstractCommand;
use ConsoleHelpers\SVNBuddy\Updater\Stability;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PharCreateCommand extends AbstractCommand
{

	/**
	 * Root folder of the project.
	 *
	 * @var string
	 */
	private $_projectRootFolder;

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
			)
			->addOption(
				'stability',
				's',
				InputOption::VALUE_REQUIRED,
				'Stability of the build (<comment>stable</comment>, <comment>snapshot</comment>, <comment>preview</comment>)'
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

		$this->_projectRootFolder = $container['project_root_folder'];
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

		if ( $optionName === 'stability' ) {
			return $this->_getStabilities();
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \RuntimeException When unknown stability was specified.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$stability = $this->_getStability();

		if ( $stability && !in_array($stability, $this->_getStabilities()) ) {
			throw new \RuntimeException('The "' . $stability . '" is unknown.');
		}

		$version = $this->_getVersion();
		$build_dir = realpath($this->io->getOption('build-dir'));

		$this->io->write('1. removing dev dependencies ... ');
		$this->_shellCommand(
			'composer',
			array(
				'install',
				'--no-interaction',
				'--no-dev',
				'--optimize-autoloader',
			),
			$this->_projectRootFolder
		);
		$this->io->writeln('done');

		$this->io->write('2. creating phar file ... ');
		$box_config = json_decode(file_get_contents($this->_projectRootFolder . '/box.json.dist'), true);

		$phar_file = $build_dir . '/' . basename($box_config['output']);
		$signature_file = $phar_file . '.sig';

		$box_config['replacements'] = array('git-version' => $version);
		$box_config['output'] = $phar_file;

		file_put_contents(
			$this->_projectRootFolder . '/box.json',
			json_encode($box_config, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0)
		);

		$box_cli = trim($this->_shellCommand('which', array('box')));
		$this->_shellCommand('php', array('-d', 'phar.readonly=0', $box_cli, 'build'), $this->_projectRootFolder);
		$this->io->writeln('done');

		$this->io->write('3. calculating phar signature ... ');
		file_put_contents(
			$signature_file,
			$this->_shellCommand('sha1sum', array(basename($phar_file)), dirname($phar_file))
		);
		$this->io->writeln('done');

		$this->io->write('4. restoring dev dependencies ... ');
		$this->_shellCommand(
			'composer',
			array(
				'install',
				'--no-interaction',
				'--optimize-autoloader',
			),
			$this->_projectRootFolder
		);
		$this->io->writeln('done');

		$this->io->writeln('Phar for <info>' . $version . '</info> version created.');
	}

	/**
	 * Returns stability.
	 *
	 * @return string|null
	 */
	private function _getStability()
	{
		return $this->io->getOption('stability');
	}

	/**
	 * Returns all stabilities.
	 *
	 * @return array
	 */
	private function _getStabilities()
	{
		return array(Stability::PREVIEW, Stability::SNAPSHOT, Stability::STABLE);
	}

	/**
	 * Returns version.
	 *
	 * @return string
	 * @throws CommandException When "stable" stability was used with non-stable version.
	 */
	private function _getVersion()
	{
		$stability = $this->_getStability();
		$git_version = $this->_getGitVersion();
		$is_unstable = preg_match('/^.*-[\d]+-g.{7}$/', $git_version);

		if ( !$stability ) {
			$stability = $is_unstable ? Stability::PREVIEW : Stability::STABLE;
		}

		if ( $is_unstable && $stability === Stability::STABLE ) {
			throw new CommandException('The "' . $git_version . '" version can\'t be used with "stable" stability.');
		}

		return $stability . ':' . $git_version;
	}

	/**
	 * Returns same version as Box does for "git-version" replacement.
	 *
	 * @return string
	 */
	private function _getGitVersion()
	{
		return trim($this->_shellCommand(
			'git',
			array('describe', 'HEAD', '--tags'),
			$this->_projectRootFolder
		));
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
		$final_arguments = $arguments;
		array_unshift($final_arguments, $command);

		$process = new Process($final_arguments, $working_directory);

		return $process->mustRun()->getOutput();
	}

}
