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


class ChoiceConfigSetting extends AbstractConfigSetting
{

	/**
	 * Choices.
	 *
	 * @var array
	 */
	private $_choices = array();

	/**
	 * Creates choice config setting.
	 *
	 * @param string  $name      Name.
	 * @param array   $choices   Choices.
	 * @param mixed   $default   Default value.
	 * @param integer $scope_bit Scope.
	 *
	 * @throws \InvalidArgumentException When no choices specified.
	 */
	public function __construct($name, array $choices, $default, $scope_bit = null)
	{
		if ( empty($choices) ) {
			throw new \InvalidArgumentException('The "$choices" parameter must not be empty.');
		}

		$this->_choices = $choices;

		parent::__construct($name, $default, $scope_bit);
	}

	/**
	 * Returns choices.
	 *
	 * @return array
	 */
	public function getChoices()
	{
		return $this->_choices;
	}

	/**
	 * Converts value into scalar for used for storage.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function convertToStorageFormat($value)
	{
		return $this->getChoiceId($value);
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
		$choice_id = $this->getChoiceId($value);

		if ( $choice_id === null ) {
			throw new \InvalidArgumentException(sprintf(
				'The "%s" config setting value must be one of "%s".',
				$this->getName(),
				implode('", "', array_keys($this->_choices))
			));
		}
	}

	/**
	 * Gets choice id from choice itself.
	 *
	 * @param mixed $choice Choice.
	 *
	 * @return mixed
	 */
	protected function getChoiceId($choice)
	{
		$choice_id = array_search((string)$choice, $this->_choices);

		if ( $choice_id !== false ) {
			return $choice_id;
		}

		return isset($this->_choices[$choice]) ? $choice : null;
	}

}
