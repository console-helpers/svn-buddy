<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy\Config;


class ConfigSetting
{

	const TYPE_STRING = 1;

	const TYPE_INTEGER = 2;

	const TYPE_ARRAY = 3;

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
	 * Data type.
	 *
	 * @var integer
	 */
	private $_dataType;

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
	 * @param integer $data_type Data type.
	 * @param mixed   $default   Default value.
	 * @param integer $scope     Scope.
	 */
	public function __construct($name, $data_type, $default, $scope = null)
	{
		$data_types = array(
			self::TYPE_STRING,
			self::TYPE_INTEGER,
			self::TYPE_ARRAY,
		);

		if ( !in_array($data_type, $data_types) ) {
			throw new \InvalidArgumentException('The "' . $data_type . '" is invalid.');
		}

		$this->_name = $name;
		$this->_dataType = $data_type;
		$this->_defaultValue = $default;

		if ( !isset($scope) ) {
			$scope = self::SCOPE_WORKING_COPY | self::SCOPE_GLOBAL;
		}

		$this->_scope = $scope;

		if ( !$this->isWithinScope(self::SCOPE_WORKING_COPY) && !$this->isWithinScope(self::SCOPE_GLOBAL) ) {
			throw new \InvalidArgumentException('The $scope must be either "working copy" or "global" or both.');
		}
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
		if ( !isset($this->_editor) ) {
			throw new \LogicException('Please use setEditor() before calling ' . __METHOD__ . '().');
		}

		if ( isset($scope_bit) ) {
			return $this->_editor->get($this->_getScopedName($scope_bit), $this->_defaultValue);
		}

		if ( $this->isWithinScope(self::SCOPE_WORKING_COPY) ) {
			$value = $this->_editor->get($this->_getScopedName(self::SCOPE_WORKING_COPY));

			if ( $value !== null ) {
				return $value;
			}
		}

		if ( $this->isWithinScope(self::SCOPE_GLOBAL) ) {
			$value = $this->_editor->get($this->_getScopedName(self::SCOPE_GLOBAL));

			if ( $value !== null ) {
				return $value;
			}
		}

		return $this->_defaultValue;
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
		if ( !isset($this->_editor) ) {
			throw new \LogicException('Please use setEditor() before calling ' . __METHOD__ . '().');
		}

		if ( $value !== null ) {
			$value = $this->_sanitize($value);
			$this->validate($value);

			if ( $this->_dataType === self::TYPE_INTEGER ) {
				$value = (int)$value;
			}
			elseif ( $this->_dataType === self::TYPE_STRING ) {
				$value = (string)$value;
			}
			elseif ( $this->_dataType === self::TYPE_ARRAY ) {
				$value = implode(PHP_EOL, $value);
			}
		}

		if ( !isset($scope_bit) ) {
			if ( $this->isWithinScope(self::SCOPE_WORKING_COPY) ) {
				$scope_bit = self::SCOPE_WORKING_COPY;
			}
			elseif ( $this->isWithinScope(self::SCOPE_GLOBAL) ) {
				$scope_bit = self::SCOPE_GLOBAL;
			}
		}

		if ( !isset($scope_bit) ) {
			throw new \LogicException('Unable to set config setting value due scope mismatch.');
		}

		// Don't store inherited value.
		if ( $value === $this->getInheritedValue($scope_bit) ) {
			$value = null;
		}

		$this->_editor->set($this->_getScopedName($scope_bit), $value);
	}

	/**
	 * Determines if value matches inherited one.
	 *
	 * @param integer $scope_bit Scope bit.
	 *
	 * @return mixed
	 */
	protected function getInheritedValue($scope_bit)
	{
		if ( $scope_bit === self::SCOPE_WORKING_COPY ) {
			return $this->getValue(self::SCOPE_GLOBAL);
		}

		return $this->_defaultValue;
	}

	/**
	 * Returns scoped config setting name.
	 *
	 * @param integer $scope Scope.
	 *
	 * @return string
	 * @throws \LogicException When working copy scoped name requested without working copy being set.
	 * @throws \InvalidArgumentException When unknown scope value is given.
	 */
	private function _getScopedName($scope)
	{
		if ( $scope === self::SCOPE_GLOBAL ) {
			return 'global-settings.' . $this->_name;
		}

		if ( $scope === self::SCOPE_WORKING_COPY ) {
			if ( !$this->_workingCopyUrl ) {
				throw new \LogicException(
					'Please call setWorkingCopyUrl() prior to calling ' . __METHOD__ . '() method.'
				);
			}

			$wc_hash = substr(hash_hmac('sha1', $this->_workingCopyUrl, 'svn-buddy'), 0, 8);

			return 'path-settings.' . $wc_hash . '.' . $this->_name;
		}

		throw new \InvalidArgumentException('The "' . $scope . '" is unknown.');
	}

	/**
	 * Sanitizes value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	private function _sanitize($value)
	{
		if ( !is_array($value) ) {
			$value = explode(PHP_EOL, $value);
		}

		$lines = array_unique(array_filter(array_map('trim', $value)));

		if ( $this->_dataType === self::TYPE_ARRAY ) {
			return $lines;
		}

		return $lines ? reset($lines) : '';
	}

	/**
	 * Performs value validation.
	 *
	 * @param mixed $value Value.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When validation failed.
	 */
	protected function validate($value)
	{
		if ( $this->_dataType === self::TYPE_INTEGER ) {
			if ( !is_numeric($value) ) {
				throw new \InvalidArgumentException('The "' . $this->_name . '" config setting must be an integer.');
			}
		}
		elseif ( $this->_dataType === self::TYPE_STRING ) {
			if ( !is_string($value) ) {
				throw new \InvalidArgumentException('The "' . $this->_name . '" config setting must be a string.');
			}
		}
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

}
