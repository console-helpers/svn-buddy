<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\CommitMessage;


class MergeTemplateFactory
{

	/**
	 * Merge templates.
	 *
	 * @var AbstractMergeTemplate[]
	 */
	protected $mergeTemplates = array();

	/**
	 * Adds merge template.
	 *
	 * @param AbstractMergeTemplate $merge_template Merge template.
	 *
	 * @return void
	 * @throws \LogicException When merge template is already added.
	 */
	public function add(AbstractMergeTemplate $merge_template)
	{
		$name = $merge_template->getName();

		if ( array_key_exists($name, $this->mergeTemplates) ) {
			throw new \LogicException('The merge template with "' . $name . '" name is already added.');
		}

		$this->mergeTemplates[$name] = $merge_template;
	}

	/**
	 * Gets merge template by name.
	 *
	 * @param string $name Merge template name.
	 *
	 * @return AbstractMergeTemplate
	 * @throws \LogicException When merge template wasn't found.
	 */
	public function get($name)
	{
		if ( !array_key_exists($name, $this->mergeTemplates) ) {
			throw new \LogicException('The merge template with "' . $name . '" name is not found.');
		}

		return $this->mergeTemplates[$name];
	}

	/**
	 * Returns merge template names.
	 *
	 * @return array
	 */
	public function getNames()
	{
		return array_keys($this->mergeTemplates);
	}

}
