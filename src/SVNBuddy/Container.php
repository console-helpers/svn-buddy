<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy;


use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\ClassicMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\InPortalMergeSourceDetector;
use ConsoleHelpers\SVNBuddy\MergeSourceDetector\MergeSourceDetectorAggregator;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Connector\UrlResolver;
use ConsoleHelpers\SVNBuddy\Repository\Parser\RevisionListParser;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;

class Container extends \ConsoleHelpers\ConsoleKit\Container
{

	/**
	 * {@inheritdoc}
	 */
	public function __construct(array $values = array())
	{
		parent::__construct($values);

		$this['app_name'] = 'SVN-Buddy';
		$this['app_version'] = '@git-version@';

		$this['working_directory_sub_folder'] = '.svn-buddy';

		$this['config_defaults'] = array(
			'repository-connector.username' => '',
			'repository-connector.password' => '',
			'repository-connector.last-revision-cache-duration' => '10 minutes',
		);

		$this['process_factory'] = function () {
			return new ProcessFactory();
		};

		$this['merge_source_detector'] = function () {
			$merge_source_detector = new MergeSourceDetectorAggregator(0);
			$merge_source_detector->add(new ClassicMergeSourceDetector(0));
			$merge_source_detector->add(new InPortalMergeSourceDetector(50));

			return $merge_source_detector;
		};

		$this['repository_url_resolver'] = function ($c) {
			return new UrlResolver($c['repository_connector']);
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

		$this['date_helper'] = function () {
			return new DateHelper();
		};

		$this['editor'] = function () {
			return new InteractiveEditor();
		};
	}

}
