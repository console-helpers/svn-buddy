<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy;


use aik099\SVNBuddy\Cache\CacheManager;
use aik099\SVNBuddy\Config\ConfigEditor;
use aik099\SVNBuddy\Helper\ContainerHelper;
use aik099\SVNBuddy\Helper\DateHelper;
use aik099\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;
use aik099\SVNBuddy\MergeSourceDetector\InPortalMergeSourceDetector;
use aik099\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use aik099\SVNBuddy\Process\ProcessFactory;
use aik099\SVNBuddy\Repository\Connector\Connector;
use aik099\SVNBuddy\Repository\Parser\RevisionListParser;
use aik099\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;
use Pimple\Container;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class DIContainer extends Container
{

	/**
	 * {@inheritdoc}
	 */
	public function __construct(array $values = array())
	{
		parent::__construct($values);

		$this['config_file'] = '{base}/config.json';
		$this['working_directory_sub_folder'] = '.svn-buddy';

		$this['working_directory'] = function ($c) {
			$working_directory = new WorkingDirectory($c['working_directory_sub_folder']);

			return $working_directory->get();
		};

		$this['config_editor'] = function ($c) {
			return new ConfigEditor(str_replace('{base}', $c['working_directory'], $c['config_file']));
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

		$this['process_factory'] = function () {
			return new ProcessFactory();
		};

		$this['merge_source_detector'] = function () {
			$merge_source_detector = new MergeSourceDetectorAggregator(0);
			$merge_source_detector->add(new ClassicMergeSourceDetector(0));
			$merge_source_detector->add(new InPortalMergeSourceDetector(50));

			return $merge_source_detector;
		};

		$this['cache_manager'] = function ($c) {
			return new CacheManager($c['working_directory']);
		};

		$this['revision_log_factory'] = function ($c) {
			return new RevisionLogFactory($c['repository_connector'], $c['cache_manager'], $c['io']);
		};

		$this['revision_list_parser'] = function () {
			return new RevisionListParser();
		};

		$this['repository_connector'] = function ($c) {
			return new Connector($c['config_editor'], $c['process_factory'], $c['io'], $c['cache_manager']);
		};

		$this['container_helper'] = function ($c) {
			return new ContainerHelper($c);
		};

		$this['date_helper'] = function () {
			return new DateHelper();
		};

		$this['editor'] = function () {
			return new InteractiveEditor();
		};
	}

}
