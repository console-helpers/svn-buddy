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


use ConsoleHelpers\SVNBuddy\Config\ArrayConfigSetting;
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\InteractiveEditor;
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
	 * Config settings.
	 *
	 * @var AbstractConfigSetting[]
	 */
	protected $configSettings = array();

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
				'Shows only given (instead of all) setting value'
			)
			->addOption(
				'edit',
				'e',
				InputOption::VALUE_REQUIRED,
				'Change setting value in the Interactive Editor'
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
		$this->configSettings = $this->getConfigSettings();
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
			return array_keys($this->configSettings);
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
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

		$config_setting = $this->getConfigSetting($setting_name);
		$value = $config_setting->getValue($this->getValueFilter());
		$retry = false;

		if ( $config_setting instanceof ArrayConfigSetting ) {
			$value = implode(PHP_EOL, $value);
		}

		do {
			try {
				$retry = false;
				$value = $this->openEditor($value);
				$config_setting->setValue($value, $this->getScopeFilter());
				$this->io->writeln('Setting <info>' . $setting_name . '</info> was edited.');
			}
			catch ( \InvalidArgumentException $e ) {
				$this->io->writeln(array('<error>' . $e->getMessage() . '</error>', ''));

				if ( $this->io->askConfirmation('Retry editing', false) ) {
					$retry = true;
				}
			}
		} while ( $retry );

		return true;
	}

	/**
	 * Opens value editing.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function openEditor($value)
	{
		return $this->_editor
			->setDocumentName('config_setting_value')
			->setContent($value)
			->launch();
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

		$config_setting = $this->getConfigSetting($setting_name);
		$config_setting->setValue(null, $this->getScopeFilter());
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
			$this->getConfigSetting($setting_name);
		}

		$extra_title = isset($setting_name) ? ' (filtered)' : '';

		if ( $this->isGlobal() ) {
			$this->io->writeln('Showing global settings' . $extra_title . ':');
		}
		else {
			$this->io->writeln(
				'Showing settings' . $extra_title . ' for <info>' . $this->getWorkingCopyUrl() . '</info> url:'
			);
		}

		$table = new Table($this->io->getOutput());

		$table->setHeaders(array(
			'Setting Name',
			'Setting Value',
		));

		$value_filter = $this->getValueFilter();

		foreach ( $this->getConfigSettingsByScope($this->getScopeFilter()) as $name => $config_setting ) {
			if ( isset($setting_name) && $name !== $setting_name ) {
				continue;
			}

			$table->addRow(array(
				$name,
				var_export($config_setting->getValue($value_filter), true),
			));
		}

		$table->render();
	}

	/**
	 * Returns config settings filtered by scope.
	 *
	 * @param integer $scope_filter Scope filter.
	 *
	 * @return AbstractConfigSetting[]
	 */
	protected function getConfigSettingsByScope($scope_filter)
	{
		$ret = array();

		foreach ( $this->configSettings as $name => $config_setting ) {
			if ( $config_setting->isWithinScope($scope_filter) ) {
				$ret[$name] = $config_setting;
			}
		}

		return $ret;
	}

	/**
	 * Validates setting name.
	 *
	 * @param string $name Setting name.
	 *
	 * @return AbstractConfigSetting
	 * @throws \InvalidArgumentException When non-existing/outside of scope setting given.
	 */
	protected function getConfigSetting($name)
	{
		if ( !array_key_exists($name, $this->configSettings) ) {
			throw new \InvalidArgumentException('The "' . $name . '" setting is unknown.');
		}

		$config_setting = $this->configSettings[$name];

		if ( !$config_setting->isWithinScope($this->getScopeFilter()) ) {
			throw new \InvalidArgumentException('The "' . $name . '" setting cannot be used in this scope.');
		}

		return $config_setting;
	}

	/**
	 * Returns scope filter for viewing config settings.
	 *
	 * @return integer
	 */
	protected function getScopeFilter()
	{
		return $this->isGlobal() ? AbstractConfigSetting::SCOPE_GLOBAL : AbstractConfigSetting::SCOPE_WORKING_COPY;
	}

	/**
	 * Returns value filter for editing config settings.
	 *
	 * @return integer
	 */
	protected function getValueFilter()
	{
		return $this->isGlobal() ? AbstractConfigSetting::SCOPE_GLOBAL : null;
	}

	/**
	 * Returns possible settings with their defaults.
	 *
	 * @return AbstractConfigSetting[]
	 */
	protected function getConfigSettings()
	{
		/** @var AbstractConfigSetting[] $config_settings */
		$config_settings = array();

		foreach ( $this->getApplication()->all() as $command ) {
			if ( $command instanceof IConfigAwareCommand ) {
				foreach ( $command->getConfigSettings() as $config_setting ) {
					$config_settings[$config_setting->getName()] = $config_setting;
				}
			}
		}

		// Allow to operate on global settings outside of working copy.
		$wc_url = $this->isGlobal() ? '' : $this->getWorkingCopyUrl();

		foreach ( $config_settings as $config_setting ) {
			$config_setting->setWorkingCopyUrl($wc_url);
			$config_setting->setEditor($this->_configEditor);
		}

		return $config_settings;
	}

	/**
	 * Determines if global only config settings should be used.
	 *
	 * @return boolean
	 */
	protected function isGlobal()
	{
		// During auto-complete the IO isn't set.
		if ( !isset($this->io) ) {
			return true;
		}

		return $this->io->getOption('global');
	}

}
