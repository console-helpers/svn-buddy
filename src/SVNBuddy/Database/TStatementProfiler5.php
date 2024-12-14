<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Database;


trait TStatementProfiler
{

	/**
	 * Turns the profiler on and off.
	 *
	 * @param boolean $active True to turn on, false to turn off.
	 *
	 * @return void
	 */
	public function setActive($active)
	{
		$this->active = (bool)$active;
	}

	/**
	 * Is the profiler active?
	 *
	 * @return boolean
	 */
	public function isActive()
	{
		return (bool)$this->active;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogLevel()
	{
		return $this->logLevel;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogLevel($logLevel)
	{
		$this->logLevel = $logLevel;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogFormat()
	{
		return $this->logFormat;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogFormat($logFormat)
	{
		$this->logFormat = $logFormat;
	}

	/**
	 * @inheritDoc
	 */
	public function start($function)
	{

	}

	/**
	 * @inheritDoc
	 */
	public function finish($statement = null, array $values = [])
	{

	}

}
