<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Config;


use ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting;
use ConsoleHelpers\SVNBuddy\Config\CommandConfig;
use ConsoleHelpers\SVNBuddy\Config\StringConfigSetting;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class CommandConfigTest extends TestCase
{

	/**
	 * Config editor.
	 *
	 * @var ObjectProphecy
	 */
	protected $configEditor;

	/**
	 * Working copy resolver.
	 *
	 * @var ObjectProphecy
	 */
	protected $workingCopyResolver;

	/**
	 * Command.
	 *
	 * @var ObjectProphecy
	 */
	protected $command;

	/**
	 * Command config.
	 *
	 * @var CommandConfig
	 */
	protected $commandConfig;

	protected function setUp()
	{
		parent::setUp();

		$this->configEditor = $this->prophesize('ConsoleHelpers\ConsoleKit\Config\ConfigEditor');
		$this->workingCopyResolver = $this->prophesize('ConsoleHelpers\SVNBuddy\Repository\WorkingCopyResolver');

		$this->command = $this->prophesize('ConsoleHelpers\SVNBuddy\Command\AbstractCommand');
		$this->command->willImplement('ConsoleHelpers\SVNBuddy\Command\IConfigAwareCommand');
		$this->command->getName()->willReturn('sample-command');

		$this->commandConfig = new CommandConfig($this->configEditor->reveal(), $this->workingCopyResolver->reveal());
	}

	public function testGetGlobalSetting()
	{
		$this->configEditor->get('global-settings.sample_name')->willReturn('sample_value');

		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_GLOBAL),
		));

		$this->assertEquals(
			'sample_value',
			$this->commandConfig->getSettingValue('sample_name', $this->command->reveal(), '')
		);
	}

	public function testGetWorkingCopySetting()
	{
		$this->configEditor->get('path-settings[svn://localhost].sample_name')->willReturn('sample_value');

		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_WORKING_COPY),
		));

		$this->workingCopyResolver->getWorkingCopyUrl('/path/to/working-copy')->willReturn('svn://localhost');

		$this->assertEquals(
			'sample_value',
			$this->commandConfig->getSettingValue('sample_name', $this->command->reveal(), '/path/to/working-copy')
		);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "sample-command" command doesn't have "missing_name" config setting.
	 */
	public function testGetNonExistingSetting()
	{
		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_GLOBAL),
		));

		$this->commandConfig->getSettingValue('missing_name', $this->command->reveal(), '');
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "sample-command" command does not have any settings.
	 */
	public function testGetSettingFromNonConfigAwareCommand()
	{
		$command = $this->prophesize('ConsoleHelpers\SVNBuddy\Command\AbstractCommand');
		$command->getName()->willReturn('sample-command');

		$this->commandConfig->getSettingValue('sample_name', $command->reveal(), '');
	}

	public function testSetGlobalSetting()
	{
		$setting_name = 'global-settings.sample_name';
		$this->configEditor->get($setting_name)->willReturn('old_value');
		$this->configEditor->set($setting_name, 'new_value')->will(function (array $args, $object) {
			$object->get($args[0])->willReturn($args[1]);
		});

		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_GLOBAL),
		));

		$this->commandConfig->setSettingValue('sample_name', $this->command->reveal(), '', 'new_value');

		$this->assertEquals(
			'new_value',
			$this->commandConfig->getSettingValue('sample_name', $this->command->reveal(), '')
		);
	}

	public function testSetWorkingCopySetting()
	{
		$this->configEditor->get('global-settings.sample_name', '')->willReturn('global_value');

		$setting_name = 'path-settings[svn://localhost].sample_name';
		$this->configEditor->get($setting_name)->willReturn('old_value');
		$this->configEditor->set($setting_name, 'new_value')->will(function (array $args, $object) {
			$object->get($args[0])->willReturn($args[1]);
		});

		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_WORKING_COPY),
		));

		$this->workingCopyResolver->getWorkingCopyUrl('/path/to/working-copy')->willReturn('svn://localhost');

		$this->commandConfig->setSettingValue(
			'sample_name',
			$this->command->reveal(),
			'/path/to/working-copy',
			'new_value'
		);

		$this->assertEquals(
			'new_value',
			$this->commandConfig->getSettingValue('sample_name', $this->command->reveal(), '/path/to/working-copy')
		);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "sample-command" command doesn't have "missing_name" config setting.
	 */
	public function testSetNonExistingSetting()
	{
		$this->command->getConfigSettings()->willReturn(array(
			new StringConfigSetting('sample_name', '', AbstractConfigSetting::SCOPE_GLOBAL),
		));

		$this->commandConfig->setSettingValue('missing_name', $this->command->reveal(), '', 'sample_value');
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "sample-command" command does not have any settings.
	 */
	public function testSetSettingToNonConfigAwareCommand()
	{
		$command = $this->prophesize('ConsoleHelpers\SVNBuddy\Command\AbstractCommand');
		$command->getName()->willReturn('sample-command');

		$this->commandConfig->setSettingValue('sample_name', $command->reveal(), '', 'sample_value');
	}

}
