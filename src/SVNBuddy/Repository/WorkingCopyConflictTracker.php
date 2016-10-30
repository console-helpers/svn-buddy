<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Command\ConflictsCommand;
use ConsoleHelpers\SVNBuddy\Config\CommandConfig;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class WorkingCopyConflictTracker
{

	/**
	 * Repository connector
	 *
	 * @var Connector
	 */
	protected $repositoryConnector;

	/**
	 * Command config.
	 *
	 * @var CommandConfig
	 */
	protected $commandConfig;

	/**
	 * Conflicts command.
	 *
	 * @var ConflictsCommand
	 */
	protected $conflictsCommand;

	/**
	 * Creates working copy conflict tracker instance.
	 *
	 * @param Connector     $repository_connector Repository connector.
	 * @param CommandConfig $command_config       Command config.
	 */
	public function __construct(Connector $repository_connector, CommandConfig $command_config)
	{
		$this->repositoryConnector = $repository_connector;
		$this->commandConfig = $command_config;
		$this->conflictsCommand = new ConflictsCommand();
	}

	/**
	 * Adds new conflicts to previously recorded.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 * @throws \LogicException When working copy has no conflicts.
	 */
	public function add($wc_path)
	{
		$new_conflicts = $this->getNewConflicts($wc_path);

		if ( !$new_conflicts ) {
			throw new \LogicException('The working copy at "' . $wc_path . '" has no conflicts to be added.');
		}

		$this->setRecordedConflicts(
			$wc_path,
			array_unique(array_merge($this->getRecordedConflicts($wc_path), $new_conflicts))
		);
	}

	/**
	 * Replaces previously recorded conflicts with new ones.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 * @throws \LogicException When working copy has no conflicts.
	 */
	public function replace($wc_path)
	{
		$new_conflicts = $this->getNewConflicts($wc_path);

		if ( !$new_conflicts ) {
			throw new \LogicException('The working copy at "' . $wc_path . '" has no conflicts to be added.');
		}

		$this->setRecordedConflicts($wc_path, $new_conflicts);
	}

	/**
	 * Erases previously recorded conflicts.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return void
	 */
	public function erase($wc_path)
	{
		$this->setRecordedConflicts($wc_path, array());
	}

	/**
	 * Returns previously recoded conflicts.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getNewConflicts($wc_path)
	{
		return $this->repositoryConnector->getWorkingCopyConflicts($wc_path);
	}

	/**
	 * Returns previously recoded conflicts.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getRecordedConflicts($wc_path)
	{
		return $this->commandConfig->getSettingValue(
			ConflictsCommand::SETTING_CONFLICTS_RECORDED_CONFLICTS,
			$this->conflictsCommand,
			$wc_path
		);
	}

	/**
	 * Returns previously recoded conflicts.
	 *
	 * @param string $wc_path   Working copy path.
	 * @param array  $conflicts Conflicts.
	 *
	 * @return void
	 */
	protected function setRecordedConflicts($wc_path, array $conflicts)
	{
		$this->commandConfig->setSettingValue(
			ConflictsCommand::SETTING_CONFLICTS_RECORDED_CONFLICTS,
			$this->conflictsCommand,
			$wc_path,
			$conflicts
		);
	}

}
