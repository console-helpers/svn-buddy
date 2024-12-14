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
	public function setActive(bool $active)
	{
		$this->active = (bool)$active;
	}

	/**
	 * Is the profiler active?
	 *
	 * @return boolean
	 */
	public function isActive(): bool
	{
		return (bool)$this->active;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogger(): \Psr\Log\LoggerInterface
	{
		return $this->logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogLevel(): string
	{
		return $this->logLevel;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogLevel(string $logLevel): void
	{
		$this->logLevel = $logLevel;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogFormat(): string
	{
		return $this->logFormat;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogFormat(string $logFormat): void
	{
		$this->logFormat = $logFormat;
	}

	/**
	 * @inheritDoc
	 */
	public function start(string $function): void
	{

	}

	/**
	 * @inheritDoc
	 */
	public function finish(?string $statement = null, array $values = []): void
	{

	}

}
