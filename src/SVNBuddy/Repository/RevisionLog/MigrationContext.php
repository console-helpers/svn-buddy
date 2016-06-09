<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\DatabaseMigration\MigrationContext as BaseMigrationContext;

class MigrationContext extends BaseMigrationContext
{

	/**
	 * Revision log.
	 *
	 * @var RevisionLog
	 */
	private $_revisionLog;

	/**
	 * Creates migration manager context.
	 *
	 * @param ExtendedPdoInterface $database     Database.
	 * @param RevisionLog          $revision_log Revision log.
	 */
	public function __construct(ExtendedPdoInterface $database, RevisionLog $revision_log)
	{
		parent::__construct($database);

		$this->_revisionLog = $revision_log;
	}

	/**
	 * Returns revision log.
	 *
	 * @return RevisionLog
	 */
	public function getRevisionLog()
	{
		return $this->_revisionLog;
	}

}
