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


use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\RevisionLogFactory;

abstract class AbstractMergeTemplate
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Revision log factory.
	 *
	 * @var RevisionLogFactory
	 */
	protected $revisionLogFactory;

	/**
	 * Creates commit message builder instance.
	 *
	 * @param Connector          $repository_connector Repository connector.
	 * @param RevisionLogFactory $revision_log_factory Revision log factory.
	 */
	public function __construct(
		Connector $repository_connector,
		RevisionLogFactory $revision_log_factory
	) {
		$this->repositoryConnector = $repository_connector;
		$this->revisionLogFactory = $revision_log_factory;
	}

	/**
	 * Returns merge template name.
	 *
	 * @return string
	 */
	abstract public function getName();

	/**
	 * Applies merge template to a working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 */
	abstract public function apply($wc_path);

	/**
	 * Flattens groups in revisions.
	 *
	 * @param array $grouped_revisions Grouped revisions.
	 *
	 * @return array
	 */
	protected function flattenMergedRevisions(array $grouped_revisions)
	{
		if ( count($grouped_revisions) > 1 ) {
			return \call_user_func_array('array_merge', $grouped_revisions);
		}

		return \array_values(reset($grouped_revisions));
	}

}
