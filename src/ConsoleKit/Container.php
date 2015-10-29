<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/console-kit
 */

namespace ConsoleHelpers\ConsoleKit;


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\ConsoleKit\Helper\ContainerHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Container extends \Pimple\Container
{

	/**
	 * {@inheritdoc}
	 */
	public function __construct(array $values = array())
	{
		parent::__construct($values);

		$this['app_name'] = 'UNKNOWN';
		$this['app_version'] = 'UNKNOWN';

		$this['config_file'] = '{base}/config.json';
		$this['config_defaults'] = array();
		$this['working_directory_sub_folder'] = '.console-kit';

		$this['working_directory'] = function ($c) {
			$working_directory = new WorkingDirectory($c['working_directory_sub_folder']);

			return $working_directory->get();
		};

		$this['config_editor'] = function ($c) {
			return new ConfigEditor(
				str_replace('{base}', $c['working_directory'], $c['config_file']),
				$c['config_defaults']
			);
		};

		$this['input'] = function () {
			return new ArgvInput();
		};

		$this['output'] = function () {
			return new ConsoleOutput();
		};

		$this['io'] = function ($c) {
			return new ConsoleIO($c['input'], $c['output'], $c['helper_set']);
		};

		// Would be replaced with actual HelperSet from extended Application class.
		$this['helper_set'] = function () {
			return new HelperSet();
		};

		$this['container_helper'] = function ($c) {
			return new ContainerHelper($c);
		};
	}

}
