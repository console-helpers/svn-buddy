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


abstract class AbstractConfigSetting
{

	const SCOPE_GLOBAL = 1;

	const SCOPE_WORKING_COPY = 2;

	/**
	 * Scope.
	 *
	 * @var integer
	 */
	private $_scope;

	/**
	 * Name.
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * Default value.
	 *
	 * @var mixed
	 */
	private $_defaultValue;

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	private $_editor;

	/**
	 * Working copy url.
	 *
	 * @var string
	 */
	private $_workingCopyUrl = '';

	/**
	 * Creates config setting instance.
	 *
	 * @param string  $name      Name.
	 * @param mixed   $default   Default value.
	 * @param integer $scope_bit Scope.
	 *
	 * @throws \InvalidArgumentException When wrong scope bit given.
	 */
	public function __construct($name, $default, $scope_bit = null)
	{
		if ( !isset($scope_bit) ) {
			$scope_bit = self::SCOPE_WORKING_COPY;
		}

		// Always add global scope.
		$this->_scope = $scope_bit | self::SCOPE_GLOBAL;

		if ( !in_array($scope_bit, array(self::SCOPE_WORKING_COPY, self::SCOPE_GLOBAL)) ) {
			throw new \InvalidArgumentException('The $scope must be either "working copy" or "global".');
		}

		$this->_name = $name;
		$this->_defaultValue = $this->convertToStorageFormat($this->normalizeValue($default));
	}

	/**
	 * Detects if config variable is within requested scope.
	 *
	 * @param integer $scope_bit Scope bit.
	 *
	 * @return boolean
	 */
	public function isWithinScope($scope_bit)
	{
		return ($this->_scope & $scope_bit) === $scope_bit;
	}

	/**
	 * Sets config editor.
	 *
	 * @param ConfigEditor $editor Config editor.
	 *
	 * @return void
	 */
	public function setEditor(ConfigEditor $editor)
	{
		$this->_editor = $editor;
	}

	/**
	 * Sets scope.
	 *
	 * @param string $wc_url Working copy url.
	 *
	 * @return void
	 */
	public function setWorkingCopyUrl($wc_url)
	{
		$this->_workingCopyUrl = $wc_url;
	}

	/**
	 * Returns config setting name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 * Returns setting value.
	 *
	 * @param integer $scope_bit Scope bit.
	 *
	 * @return mixed
	 */
	public function getValue($scope_bit = null)
	{
		$this->assertUsage(__METHOD__, $scope_bit);

		if ( isset($scope_bit) ) {
			$value = $this->_editor->get(
				$this->_getScopedName(__METHOD__, $scope_bit),
				$this->_defaultValue
			);
		}
		else {
			$value = null;

			if ( $this->isWithinScope(self::SCOPE_WORKING_COPY) ) {
				$value = $this->_editor->get(
					$this->_getScopedName(__METHOD__, self::SCOPE_WORKING_COPY)
				);
			}

			if ( $value === null ) {
				$value = $this->_editor->get(
					$this->_getScopedName(__METHOD__, self::SCOPE_GLOBAL)
				);
			}

			if ( $value === null ) {
				$value = $this->_defaultValue;
			}
		}

		return $this->normalizeValue($value);
	}

	/**
	 * Changes setting value.
	 *
	 * @param mixed   $value     Value.
	 * @param integer $scope_bit Scope bit.
	 *
	 * @return void
	 * @throws \LogicException When no matching scope was found.
	 */
	public function setValue($value, $scope_bit = null)
	{
		$this->assertUsage(__METHOD__, $scope_bit);

		if ( $value !== null ) {
			$value = $this->normalizeValue($value);
			$this->validate($value);
			$value = $this->convertToStorageFormat($value);
		}

		if ( !isset($scope_bit) ) {
			if ( $this->isWithinScope(self::SCOPE_WORKING_COPY) ) {
				$scope_bit = self::SCOPE_WORKING_COPY;
			}
			else {
				$scope_bit = self::SCOPE_GLOBAL;
			}
		}

		// Don't store inherited value.
		if ( $value === $this->_getInheritedValue(__METHOD__, $scope_bit) ) {
			$value = null;
		}

		$this->_editor->set($this->_getScopedName(__METHOD__, $scope_bit), $value);
	}

	/**
	 * Determines if config setting is being used correctly.
	 *
	 * @param string  $caller_method Caller method.
	 * @param integer $scope_bit     Scope bit.
	 *
	 * @return void
	 * @throws \LogicException When no editor was set upfront.
	 * @throws \InvalidArgumentException When $scope_bit isn't supported by config setting.
	 */
	public function assertUsage($caller_method, $scope_bit = null)
	{
		if ( !isset($this->_editor) ) {
			throw new \LogicException('Please use setEditor() before calling ' . $caller_method . '().');
		}

		if ( isset($scope_bit) && !$this->isWithinScope($scope_bit) ) {
			$error_msg = 'The usage of "%s" scope bit for "%s" config setting is forbidden.';
			throw new \InvalidArgumentException(sprintf($error_msg, $scope_bit, $this->getName()));
		}
	}

	/**
	 * Determines if value matches inherited one.
	 *
	 * @param string  $caller_method Caller method.
	 * @param integer $scope_bit     Scope bit.
	 *
	 * @return mixed
	 */
	private function _getInheritedValue($caller_method, $scope_bit)
	{
		if ( $scope_bit === self::SCOPE_WORKING_COPY ) {
			return $this->_editor->get($this->_getScopedName($caller_method, self::SCOPE_GLOBAL), $this->_defaultValue);
		}

		return $this->_defaultValue;
	}

	/**
	 * Returns scoped config setting name.
	 *
	 * @param string  $caller_method Caller method.
	 * @param integer $scope         Scope.
	 *
	 * @return string
	 * @throws \LogicException When working copy scoped name requested without working copy being set.
	 */
	private function _getScopedName($caller_method, $scope)
	{
		if ( $scope === self::SCOPE_GLOBAL ) {
			return 'global-settings.' . $this->_name;
		}

		if ( !$this->_workingCopyUrl ) {
			throw new \LogicException(
				'Please call setWorkingCopyUrl() prior to calling ' . $caller_method . '() method.'
			);
		}

		$wc_hash = substr(hash_hmac('sha1', $this->_workingCopyUrl, 'svn-buddy'), 0, 8);

		return 'path-settings.' . $wc_hash . '.' . $this->_name;
	}

	/**
	 * Normalizes value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function normalizeValue($value)
	{
		return $value;
	}

	/**
	 * Performs value validation.
	 *
	 * @param mixed $value Value.
	 *
	 * @return void
	 */
	abstract protected function validate($value);

	/**
	 * Converts value into scalar for used for storage.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	abstract protected function convertToStorageFormat($value);

}
