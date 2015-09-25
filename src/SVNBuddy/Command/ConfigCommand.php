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


use aik099\SVNBuddy\Config;
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
	 * Config.
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Prefix to prepend before all setting names.
	 *
	 * @var
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
		$this->_config = $container['config'];
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
			return $this->getSettings();
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$wc_path = $this->getWorkingCopyPath();
		$this->prepareSettingPrefix($wc_path);

		if ( $this->processShow() || $this->processEdit() || $this->processDelete() ) {
			return;
		}
		else {
			$this->listSettings();
		}
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
		$value = $this->_config->get($this->settingPrefix . $setting_name, '');
		$edited_value = $this->_editor
			->setDocumentName('config_option_value')
			->setContent($value)
			->launch();
		$this->_config->set($this->settingPrefix . $setting_name, trim($edited_value));
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
		$this->_config->set($this->settingPrefix . $setting_name, null);
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
			$setting_value = $this->_config->get($this->settingPrefix . $setting_name);

			if ( $setting_value === null ) {
				$this->io->writeln('Setting <info>' . $setting_name . '</info> is not set.');

				return;
			}
			else {
				$settings = array(
					$setting_name => $setting_value,
				);
			}
		}
		else {
			$settings = $this->_config->get($this->settingPrefix);
		}

		if ( !$settings ) {
			$this->io->writeln('No settings found.');

			return;
		}

		$table = new Table($this->io->getOutput());

		$table->setHeaders(array(
			'Setting Name',
			'Setting Value',
		));

		foreach ( $settings as $name => $value ) {
			$name = preg_replace('/^' . preg_quote($this->settingPrefix, '/') . '/', '', $name);

			$table->addRow(array(
				$name,
				$value,
			));
		}

		$table->render();
	}

	/**
	 * Prepare setting prefix.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 */
	protected function prepareSettingPrefix($wc_path)
	{
		if ( $this->isGlobal() ) {
			$this->settingPrefix = 'global-settings.';
		}
		else {
			$wc_url = $this->repositoryConnector->getWorkingCopyUrl($wc_path);
			$wc_hash = substr(hash_hmac('sha1', $wc_url, 'svn-buddy'), 0, 8);

			$this->settingPrefix = 'path-settings.' . $wc_hash . '.';
		}
	}

	/**
	 * Determines if we're operating on global settings.
	 *
	 * @return boolean
	 */
	protected function isGlobal()
	{
		return $this->io->getOption('global');
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
		if ( !in_array($name, $this->getSettings()) ) {
			throw new \InvalidArgumentException('The "' . $name . '" setting is unknown.');
		}
	}

	/**
	 * Returns possible settings.
	 *
	 * @return array
	 */
	protected function getSettings()
	{
		$ret = array();

		foreach ( $this->getApplication()->all() as $command ) {
			if ( $command instanceof IConfigAwareCommand ) {
				$ret = array_merge($ret, $command->getConfigSettings());
			}
		}

		return $ret;
	}

}
