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

	/**
	 * Scope.
	 *
	 * @var string
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
	private $_default;

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	private $_editor;

	/**
	 * Creates config setting instance.
	 *
	 * @param string  $name      Name.
	 * @param integer $data_type Data type.
	 * @param mixed   $default   Default value.
	 */
	public function __construct($name, $data_type, $default)
	{
		$data_types = array(
			self::TYPE_STRING,
			self::TYPE_INTEGER,
			self::TYPE_ARRAY,
		);

		if ( !in_array($data_type, $data_types) ) {
			throw new \InvalidArgumentException('The "' . $data_type . '" is invalid');
		}

		$this->_name = $name;
		$this->_dataType = $data_type;
		$this->_default = $default;
	}

	/**
	 * Sets scope.
	 *
	 * @param string $scope Scope.
	 *
	 * @return void
	 */
	public function setScope($scope)
	{
		$this->_scope = $scope;
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
	 * @param boolean $no_default No default value fallback on missing value.
	 *
	 * @return mixed
	 */
	public function getValue($no_default = false)
	{
		if ( !isset($this->_editor) ) {
			throw new \LogicException('Please use setEditor() before calling ' . __METHOD__ . '().');
		}

		$default = $no_default ? null : $this->_default;

		return $this->_editor->get($this->_scope . $this->_name, $default);
	}

	/**
	 * Determines if value has been set.
	 *
	 * @return boolean
	 */
	public function hasValue()
	{
		return $this->getValue(true) !== null;
	}

	/**
	 * Changes setting value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return void
	 */
	public function setValue($value)
	{
		if ( !isset($this->_editor) ) {
			throw new \LogicException('Please use setEditor() before calling ' . __METHOD__ . '().');
		}

		$value = $this->_sanitize($value);

		if ( $value !== null ) {
			if ( $this->_dataType === self::TYPE_INTEGER ) {
				if ( !is_numeric($value) ) {
					throw new \InvalidArgumentException('The "' . $this->_name . '" config setting must be an integer.');
				}

				$value = (int)$value;
			}
			elseif ( $this->_dataType === self::TYPE_STRING ) {
				if ( !is_string($value) ) {
					throw new \InvalidArgumentException('The "' . $this->_name . '" config setting must be a string.');
				}
			}
			else {
				$value = implode(PHP_EOL, $value);
			}
		}

		$this->_editor->set($this->_scope . $this->_name, $value);
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
		$lines = array_filter(array_map('trim', explode(PHP_EOL, $value)));

		if ( !count($lines) ) {
			return null;
		}

		if ( $this->_dataType === self::TYPE_ARRAY ) {
			return $lines;
		}

		return reset($lines);
	}

	/**
	 * Returns config setting default value.
	 *
	 * @return mixed
	 */
	public function getDefault()
	{
		return $this->_default;
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
