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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\ConsoleHelpers\SVNBuddy\ProphecyToken\ConfigStorageNameToken;

abstract class AbstractConfigSettingTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Config editor
	 *
	 * @var ObjectProphecy
	 */
	protected $configEditor;

	/**
	 * Class of config setting.
	 *
	 * @var string
	 */
	protected $className;

	/**
	 * Default value.
	 *
	 * @var string
	 */
	protected $defaultValue;

	protected function setUp()
	{
		parent::setUp();

		$this->configEditor = $this->prophesize('ConsoleHelpers\\SVNBuddy\Config\\ConfigEditor');
	}

	/**
	 * @dataProvider scopeDataProvider
	 */
	public function testCreateWithCorrectScope($scope_bit, array $scope_bits)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		foreach ( $scope_bits as $check_scope_bit => $check_value ) {
			$this->assertSame(
				$check_value,
				$config_setting->isWithinScope($check_scope_bit),
				'The "' . $scope_bit . '" expands into "' . $check_scope_bit . '" scope bit.'
			);
		}
	}

	public function scopeDataProvider()
	{
		return array(
			'global scope bit' => array(
				AbstractConfigSetting::SCOPE_GLOBAL,
				array(AbstractConfigSetting::SCOPE_GLOBAL => true, AbstractConfigSetting::SCOPE_WORKING_COPY => false),
			),
			'working copy scope bit' => array(
				AbstractConfigSetting::SCOPE_WORKING_COPY,
				array(AbstractConfigSetting::SCOPE_WORKING_COPY => true, AbstractConfigSetting::SCOPE_GLOBAL => true),
			),
			'no scope bit' => array(
				null,
				array(AbstractConfigSetting::SCOPE_WORKING_COPY => true, AbstractConfigSetting::SCOPE_GLOBAL => true),
			),
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The $scope must be either "working copy" or "global".
	 * @dataProvider incorrectScopeDataProvider
	 */
	public function testCreateWithIncorrectScope($scope)
	{
		$this->createConfigSetting($scope);
	}

	public function incorrectScopeDataProvider()
	{
		return array(
			'empty scope bit' => array(0),
			'mixed scope bits' => array(
				AbstractConfigSetting::SCOPE_GLOBAL | AbstractConfigSetting::SCOPE_WORKING_COPY,
			),
		);
	}

	public function testGetName()
	{
		$config_setting = $this->createConfigSetting();

		$this->assertEquals('name', $config_setting->getName());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please use setEditor() before calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::getValue().
	 */
	public function testGetValueWithoutEditor()
	{
		$this->createConfigSetting(null, false, null)->getValue();
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The usage of "2" scope bit for "name" config setting is forbidden.
	 */
	public function testGetValueWithForbiddenScopeBit()
	{
		$this
			->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL)
			->getValue(AbstractConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueWithCorrectScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting();

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->configEditor
			->get(
				new ConfigStorageNameToken('name', $scope_bit),
				$this->convertToStorage($this->defaultValue)
			)
			->willReturn($this->getSampleValue($scope_bit, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue($scope_bit), $config_setting->getValue($scope_bit));
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testGetValueFromWithoutFallback($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');
		}

		$this->configEditor
			->get(new ConfigStorageNameToken('name', $scope_bit))
			->willReturn($this->getSampleValue($scope_bit, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue($scope_bit), $config_setting->getValue());
	}

	public function testGetValueFromWorkingCopyWithFallbackToGlobal()
	{
		$config_setting = $this->createConfigSetting();
		$config_setting->setWorkingCopyUrl('url');

		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_WORKING_COPY))
			->shouldBeCalled();

		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL))
			->willReturn($this->getSampleValue(AbstractConfigSetting::SCOPE_GLOBAL, true))
			->shouldBeCalled();

		$this->assertEquals($this->getSampleValue(AbstractConfigSetting::SCOPE_GLOBAL), $config_setting->getValue());
	}

	public function testGetValueFromGlobalWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL))
			->shouldBeCalled();

		$this->assertEquals($this->defaultValue, $config_setting->getValue());
	}

	public function testGetValueFromWorkingCopyWithFallbackToDefault()
	{
		$config_setting = $this->createConfigSetting();
		$config_setting->setWorkingCopyUrl('url');

		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_WORKING_COPY))
			->shouldBeCalled();

		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL))
			->shouldBeCalled();

		$this->assertEquals($this->defaultValue, $config_setting->getValue());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please use setEditor() before calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::setValue().
	 */
	public function testSetValueWithoutEditor()
	{
		$this->createConfigSetting(null, false, null)->setValue('value');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage The usage of "2" scope bit for "name" config setting is forbidden.
	 */
	public function testSetValueWithForbiddenScopeBit()
	{
		$this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL)->setValue('value', AbstractConfigSetting::SCOPE_WORKING_COPY);
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithoutScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->get(
					new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($this->defaultValue)
				)
				->shouldBeCalled();
		}

		$expected_value = $this->getSampleValue($scope_bit);

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), $this->convertToStorage($expected_value))
			->shouldBeCalled();

		$config_setting->setValue($this->getSampleValue($scope_bit));
	}

	/**
	 * @dataProvider scopeBitDataProvider
	 */
	public function testSetValueWithScopeBit($scope_bit)
	{
		$config_setting = $this->createConfigSetting($scope_bit);

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->get(
					new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($this->defaultValue)
				)
				->shouldBeCalled();
		}

		$expected_value = $this->getSampleValue($scope_bit);

		$this->configEditor
			->set(new ConfigStorageNameToken('name', $scope_bit), $this->convertToStorage($expected_value))
			->shouldBeCalled();

		$config_setting->setValue($this->getSampleValue($scope_bit), $scope_bit);
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please call setWorkingCopyUrl() prior to calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::getValue() method.
	 */
	public function testGetValueWithoutWorkingCopy()
	{
		$this->createConfigSetting()->getValue();
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage Please call setWorkingCopyUrl() prior to calling ConsoleHelpers\SVNBuddy\Config\AbstractConfigSetting::setValue() method.
	 */
	public function testSetValueWithoutWorkingCopy()
	{
		$this->createConfigSetting()->setValue($this->getSampleValue(AbstractConfigSetting::SCOPE_WORKING_COPY));
	}

	/**
	 * @dataProvider normalizationValueDataProvider
	 */
	public function testSetValueNormalization($value, $normalized_value)
	{
		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);

		$this->configEditor
			->set(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL), Argument::any())
			->will(function (array $args, $config_editor) {
				$config_editor->get($args[0])->willReturn($args[1])->shouldBeCalled();
			})
			->shouldBeCalled();

		$config_setting->setValue($value);

		$this->assertSame($normalized_value, $config_setting->getValue());
	}

	abstract public function normalizationValueDataProvider($test_name, $a_value = null, $b_value = null);

	public function scopeBitDataProvider()
	{
		return array(
			'working copy scope' => array(AbstractConfigSetting::SCOPE_WORKING_COPY),
			'global scope' => array(AbstractConfigSetting::SCOPE_GLOBAL),
		);
	}

	/**
	 * @dataProvider setValueWithInheritanceDataProvider
	 */
	public function testSetValueWithInheritance($scope_bit, array $defaults)
	{
		$wc_value = $defaults[0];
		$global_value = $defaults[1];

		if ( $scope_bit === AbstractConfigSetting::SCOPE_WORKING_COPY ) {
			$this->configEditor
				->get(
					new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL),
					$this->convertToStorage($global_value)
				)
				->willReturn(
					$this->convertToStorage($wc_value)
				)
				->shouldBeCalled();

			$config_setting = $this->createConfigSetting($scope_bit, $global_value);
			$config_setting->setWorkingCopyUrl('url');

			$this->configEditor
				->set(new ConfigStorageNameToken('name', $scope_bit), null)
				->shouldBeCalled();

			$config_setting->setValue($wc_value);
		}
		else {
			$this->configEditor
				->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL), $global_value)
				->shouldNotBeCalled();

			$config_setting = $this->createConfigSetting($scope_bit, $global_value);

			$this->configEditor
				->set(new ConfigStorageNameToken('name', $scope_bit), null)
				->shouldBeCalled();

			$config_setting->setValue($global_value);
		}
	}

	abstract public function setValueWithInheritanceDataProvider($test_name, $a_value = null, $b_value = null);

	/**
	 * @dataProvider storageDataProvider
	 */
	public function testStorage($user_value, $stored_value)
	{
		$this->configEditor
			->set(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL), $stored_value)
			->shouldBeCalled();

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL);
		$config_setting->setValue($user_value);
	}

	/**
	 * @dataProvider storageDataProvider
	 */
	public function testDefaultValueIsConvertedToScalar($default_value, $stored_value)
	{
		$this->configEditor
			->get(new ConfigStorageNameToken('name', AbstractConfigSetting::SCOPE_GLOBAL), $stored_value)
			->willReturn(null)
			->shouldBeCalled();

		$config_setting = $this->createConfigSetting(AbstractConfigSetting::SCOPE_GLOBAL, $default_value);
		$config_setting->getValue(AbstractConfigSetting::SCOPE_GLOBAL);
	}

	abstract public function storageDataProvider($test_name, $a_value = null, $b_value = null);

	/**
	 * Creates config setting.
	 *
	 * @param integer $scope_bit     Scope bit.
	 * @param mixed   $default_value Default value.
	 * @param boolean $with_editor   Connect editor to the setting.
	 *
	 * @return AbstractConfigSetting
	 * @throws \LogicException When config setting class not set.
	 */
	protected function createConfigSetting(
		$scope_bit = null,
		$default_value = null,
		$with_editor = true
	) {
		if ( !isset($default_value) ) {
			$default_value = $this->defaultValue;
		}

		if ( !isset($this->className) ) {
			throw new \LogicException('Please specify "$this->className" first.');
		}

		$class = $this->className;
		$config_setting = new $class('name', $default_value, $scope_bit);

		if ( $with_editor ) {
			$config_setting->setEditor($this->configEditor->reveal());
		}

		return $config_setting;
	}

	/**
	 * Returns sample value based on scope, that would pass config setting validation.
	 *
	 * @param mixed $scope_bit Scope bit.
	 * @param boolean $as_stored Return value in storage format.
	 *
	 * @return mixed
	 */
	abstract protected function getSampleValue($scope_bit, $as_stored = false);

	/**
	 * Converts value to storage format.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function convertToStorage($value)
	{
		return $value;
	}

}
