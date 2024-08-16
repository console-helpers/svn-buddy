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
use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\ChoiceConfigSetting;
use ConsoleHelpers\SVNBuddy\InteractiveEditor;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\AbstractMergeTemplate;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\CommitMessageBuilder;
use ConsoleHelpers\SVNBuddy\Repository\CommitMessage\MergeTemplateFactory;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyConflictTracker;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommitCommand extends AbstractCommand implements IConfigAwareCommand
{

	const SETTING_COMMIT_MERGE_TEMPLATE = 'commit.merge-template';

	const STOP_LINE = '--This line, and those below, will be ignored--';

	/**
	 * Editor.
	 *
	 * @var InteractiveEditor
	 */
	private $_editor;

	/**
	 * Commit message builder.
	 *
	 * @var CommitMessageBuilder
	 */
	private $_commitMessageBuilder;

	/**
	 * Merge template factory.
	 *
	 * @var MergeTemplateFactory
	 */
	private $_mergeTemplateFactory;

	/**
	 * Working copy conflict tracker.
	 *
	 * @var WorkingCopyConflictTracker
	 */
	private $_workingCopyConflictTracker;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('commit')
			->setDescription(
				'Send changes from your working copy to the repository'
			)
			->setAliases(array('ci'))
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'cl',
				null,
				InputOption::VALUE_NONE,
				'Operate only on members of selected changelist'
			)
			->addOption(
				'merge-template',
				null,
				InputOption::VALUE_REQUIRED,
				'Use alternative merge template for this commit'
			)
			->addOption(
				'deploy',
				'd',
				InputOption::VALUE_NONE,
				'Perform remote deployment after a successful commit'
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

		if ( $optionName === 'merge-template' ) {
			return $this->getMergeTemplateNames();
		}

		return $ret;
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

		$this->_editor = $container['editor'];
		$this->_commitMessageBuilder = $container['commit_message_builder'];
		$this->_mergeTemplateFactory = $container['merge_template_factory'];
		$this->_workingCopyConflictTracker = $container['working_copy_conflict_tracker'];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws CommandException When conflicts are detected.
	 * @throws CommandException Working copy has no changes.
	 * @throws CommandException User decides not to perform a commit.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$conflicts = $this->_workingCopyConflictTracker->getNewConflicts($wc_path);

		if ( $conflicts ) {
			throw new CommandException('Conflicts detected. Please resolve them before committing.');
		}

		$changelist = $this->getChangelist($wc_path);
		$compact_working_copy_status = $this->repositoryConnector->getCompactWorkingCopyStatus($wc_path, $changelist);

		if ( !$compact_working_copy_status ) {
			// Deploy instead of failing.
			if ( $this->deploy() ) {
				return;
			}

			throw new CommandException('Nothing to commit.');
		}

		$commit_message = $this->_commitMessageBuilder->build($wc_path, $this->getMergeTemplate(), $changelist);
		$commit_message .= PHP_EOL . PHP_EOL . self::STOP_LINE . PHP_EOL . PHP_EOL . $compact_working_copy_status;

		$edited_commit_message = $this->_editor
			->setDocumentName('commit_message')
			->setContent($commit_message)
			->launch();

		$stop_line_pos = strpos($edited_commit_message, self::STOP_LINE);

		if ( $stop_line_pos !== false ) {
			$edited_commit_message = trim(substr($edited_commit_message, 0, $stop_line_pos));
		}

		$this->io->writeln(array('<fg=white;options=bold>Commit message:</>', $edited_commit_message, ''));

		if ( !$this->io->askConfirmation('Run "svn commit"', false) ) {
			throw new CommandException('Commit aborted by user.');
		}

		$tmp_file = tempnam(sys_get_temp_dir(), 'commit_message_');
		file_put_contents($tmp_file, $edited_commit_message);

		$arguments = array('-F', $tmp_file);

		if ( strlen($changelist) ) {
			$arguments[] = '--depth';
			$arguments[] = 'empty';

			// Relative path used to make command line shorter.
			foreach ( array_keys($this->repositoryConnector->getWorkingCopyStatus($wc_path, $changelist)) as $path ) {
				$arguments[] = $path;
			}
		}
		else {
			$arguments[] = $wc_path;
		}

		$this->repositoryConnector->getCommand('commit', $arguments)->runLive(array(
			'/(Committed revision [\d]+\.)/' => '<fg=white;options=bold>$1</>',
		));
		$this->_workingCopyConflictTracker->erase($wc_path);
		unlink($tmp_file);

		// Make committed revision instantly available for merging.
		$this->getRevisionLog($this->getWorkingCopyUrl())->setForceRefreshFlag(true);

		$this->io->writeln('<info>Done</info>');

		$this->deploy();
	}

	/**
	 * Performs a deploy.
	 *
	 * @return boolean
	 */
	protected function deploy()
	{
		if ( !$this->io->getOption('deploy') ) {
			return false;
		}

		$this->runOtherCommand('deploy', array('--remote' => true));

		return true;
	}

	/**
	 * Returns user selected changelist.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string|null
	 * @throws CommandException When no changelists found.
	 */
	protected function getChangelist($wc_path)
	{
		if ( !$this->io->getOption('cl') ) {
			return null;
		}

		$changelists = $this->repositoryConnector->getWorkingCopyChangelists($wc_path);

		if ( !$changelists ) {
			throw new CommandException('No changelists detected.');
		}

		return $this->io->choose(
			'Pick changelist by number [0]:',
			$changelists,
			0,
			'Changelist "%s" is invalid.'
		);
	}

	/**
	 * Returns merge template to use.
	 *
	 * @return AbstractMergeTemplate
	 */
	protected function getMergeTemplate()
	{
		$merge_template_name = $this->io->getOption('merge-template');

		if ( !isset($merge_template_name) ) {
			$merge_template_name = $this->getSetting(self::SETTING_COMMIT_MERGE_TEMPLATE);
		}

		return $this->_mergeTemplateFactory->get($merge_template_name);
	}

	/**
	 * Returns merge template names.
	 *
	 * @return array
	 */
	protected function getMergeTemplateNames()
	{
		if ( isset($this->_mergeTemplateFactory) ) {
			return $this->_mergeTemplateFactory->getNames();
		}

		// When used from "getConfigSettings" method.
		$container = $this->getContainer();

		return $container['merge_template_factory']->getNames();
	}

	/**
	 * Returns list of config settings.
	 *
	 * @return AbstractConfigSetting[]
	 */
	public function getConfigSettings()
	{
		$merge_template_names = array();

		foreach ( $this->getMergeTemplateNames() as $merge_template_name ) {
			$merge_template_names[$merge_template_name] = str_replace('_', ' ', ucfirst($merge_template_name));
		}

		return array(
			new ChoiceConfigSetting(
				self::SETTING_COMMIT_MERGE_TEMPLATE,
				$merge_template_names,
				reset($merge_template_names)
			),
		);
	}

}
