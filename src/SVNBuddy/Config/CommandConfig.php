<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Config;


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Command\AbstractCommand;
use ConsoleHelpers\SVNBuddy\Command\IConfigAwareCommand;
use ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver;

class CommandConfig
{

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	protected $configEditor;

	/**
	 * Working copy resolver.
	 *
	 * @var WorkingCopyResolver
	 */
	protected $workingCopyResolver;

	/**
	 * Creates command configurator instance.
	 *
	 * @param ConfigEditor        $config_editor         Config editor.
	 * @param WorkingCopyResolver $working_copy_resolver Working copy resolver.
	 */
	public function __construct(ConfigEditor $config_editor, WorkingCopyResolver $working_copy_resolver)
	{
		$this->configEditor = $config_editor;
		$this->workingCopyResolver = $working_copy_resolver;
	}

	/**
	 * Returns command setting value.
	 *
	 * @param string          $name     Name.
	 * @param AbstractCommand $command  Command to get settings from.
	 * @param string          $raw_path Raw path.
	 *
	 * @return mixed
	 */
	public function getSettingValue($name, AbstractCommand $command, $raw_path)
	{
		return $this->getSetting($name, $command, $raw_path)->getValue();
	}

	/**
	 * Sets command setting value.
	 *
	 * @param string          $name     Name.
	 * @param AbstractCommand $command  Command to get settings from.
	 * @param string          $raw_path Raw path.
	 * @param mixed           $value    Value.
	 *
	 * @return void
	 */
	public function setSettingValue($name, AbstractCommand $command, $raw_path, $value)
	{
		$this->getSetting($name, $command, $raw_path)->setValue($value);
	}

	/**
	 * Validates command setting usage.
	 *
	 * @param string          $name     Name.
	 * @param AbstractCommand $command  Command to get settings from.
	 * @param string          $raw_path Raw path.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When command don't have any config settings to provide.
	 */
	protected function getSetting($name, AbstractCommand $command, $raw_path)
	{
		if ( !($command instanceof IConfigAwareCommand) ) {
			throw new \LogicException('The "' . $command->getName() . '" command does not have any settings.');
		}

		$config_setting = $this->findSetting($name, $command->getConfigSettings(), $command->getName());

		if ( $config_setting->isWithinScope(AbstractConfigSetting::SCOPE_WORKING_COPY) ) {
			$config_setting->setWorkingCopyUrl(
				$this->workingCopyResolver->getWorkingCopyUrl($raw_path)
			);
		}

		$config_setting->setEditor($this->configEditor);

		return $config_setting;
	}

	/**
	 * Searches for a config setting with a given name.
	 *
	 * @param string                  $name            Config setting name.
	 * @param AbstractConfigSetting[] $config_settings Config settings.
	 * @param string                  $command_name    Command name.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When config setting is not found.
	 */
	protected function findSetting($name, array $config_settings, $command_name)
	{
		foreach ( $config_settings as $config_setting ) {
			if ( $config_setting->getName() === $name ) {
				return $config_setting;
			}
		}

		throw new \LogicException(
			'The "' . $command_name . '" command doesn\'t have "' . $name . '" config setting.'
		);
	}

}
