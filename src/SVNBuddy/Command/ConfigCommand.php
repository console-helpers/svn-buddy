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


use aik099\SVNBuddy\Config\ConfigEditor;
use aik099\SVNBuddy\InteractiveEditor;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends AbstractCommand implements IAggregatorAwareCommand
{

	/**
	 * Editor.
	 *
	 * @var InteractiveEditor
	 */
	private $_editor;

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	private $_configEditor;

	/**
	 * Prefix to prepend before all setting names.
	 *
	 * @var string
	 */
	protected $settingPrefix;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$description = <<<TEXT
TODO
TEXT;

		$this
			->setName('config')
			->setDescription('Get and set working copy or global settings')
			->setHelp($description)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Working copy path',
				'.'
			)
			->addOption(
				'show',
				's',
				InputOption::VALUE_REQUIRED,
				'Show setting value'
			)
			->addOption(
				'edit',
				'e',
				InputOption::VALUE_REQUIRED,
				'Change setting value in Interactive Editor'
			)
			->addOption(
				'delete',
				'd',
				InputOption::VALUE_REQUIRED,
				'Delete setting'
			)
			->addOption(
				'global',
				'g',
				InputOption::VALUE_NONE,
				'Operate on global instead of working copy-specific settings'
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

		$this->_editor = $container['editor'];
		$this->_configEditor = $container['config_editor'];
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

		if ( in_array($optionName, array('show', 'edit', 'delete')) ) {
			return array_keys($this->getSettings());
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->settingPrefix = $this->getSettingPrefix($this->io->getOption('global'));

		if ( $this->processShow() || $this->processEdit() || $this->processDelete() ) {
			return;
		}

		$this->listSettings();
	}

	/**
	 * Shows setting value.
	 *
	 * @return boolean
	 */
	protected function processShow()
	{
		$setting_name = $this->io->getOption('show');

		if ( $setting_name === null ) {
			return false;
		}

		$this->listSettings($setting_name);

		return true;
	}

	/**
	 * Changes setting value.
	 *
	 * @return boolean
	 */
	protected function processEdit()
	{
		$setting_name = $this->io->getOption('edit');

		if ( $setting_name === null ) {
			return false;
		}

		$this->validateSetting($setting_name);
		$value = $this->_configEditor->get($this->settingPrefix . $setting_name, '');
		$edited_value = $this->_editor
			->setDocumentName('config_option_value')
			->setContent($value)
			->launch();
		$this->_configEditor->set($this->settingPrefix . $setting_name, trim($edited_value));
		$this->io->writeln('Setting <info>' . $setting_name . '</info> was edited.');

		return true;
	}

	/**
	 * Deletes setting value.
	 *
	 * @return boolean
	 */
	protected function processDelete()
	{
		$setting_name = $this->io->getOption('delete');

		if ( $setting_name === null ) {
			return false;
		}

		$this->validateSetting($setting_name);
		$this->_configEditor->set($this->settingPrefix . $setting_name, null);
		$this->io->writeln('Setting <info>' . $setting_name . '</info> was deleted.');

		return true;
	}

	/**
	 * Lists values for every stored setting.
	 *
	 * @param string $setting_name Setting name.
	 *
	 * @return void
	 */
	protected function listSettings($setting_name = null)
	{
		if ( isset($setting_name) ) {
			$this->validateSetting($setting_name);
		}

		$extra_title = isset($setting_name) ? ' (filtered)' : '';

		if ( $this->io->getOption('global') ) {
			$this->io->writeln('Showing global settings' . $extra_title . ':');
		}
		else {
			$this->io->writeln(
				'Showing settings' . $extra_title . ' for <info>' . $this->getWorkingCopyPath() . '</info> path:'
			);
		}

		$settings = $this->_configEditor->get($this->settingPrefix);
		$table = new Table($this->io->getOutput());

		$table->setHeaders(array(
			'Setting Name',
			'Setting Value',
			'User Override',
		));

		foreach ( $this->getSettings() as $name => $default ) {
			if ( isset($setting_name) && $name !== $setting_name ) {
				continue;
			}

			if ( array_key_exists($this->settingPrefix . $name, $settings) ) {
				$user_override = true;
				$value = $settings[$this->settingPrefix . $name];
			}
			else {
				$user_override = false;
				$value = $default;
			}

			$table->addRow(array(
				preg_replace('/^' . preg_quote($this->settingPrefix, '/') . '/', '', $name),
				var_export($value, true),
				$user_override ? 'Yes' : 'No',
			));
		}

		$table->render();
	}

	/**
	 * Validates setting name.
	 *
	 * @param string $name Setting name.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When non-existing setting given.
	 */
	protected function validateSetting($name)
	{
		if ( !array_key_exists($name, $this->getSettings()) ) {
			throw new \InvalidArgumentException('The "' . $name . '" setting is unknown.');
		}
	}

	/**
	 * Returns possible settings with their defaults.
	 *
	 * @return array
	 */
	protected function getSettings()
	{
		$ret = array();

		foreach ( $this->getApplication()->all() as $command ) {
			if ( $command instanceof IConfigAwareCommand ) {
				foreach ( $command->getConfigSettings() as $setting_name => $setting_default ) {
					$ret[$setting_name] = $setting_default;
				}
			}
		}

		return $ret;
	}

}
