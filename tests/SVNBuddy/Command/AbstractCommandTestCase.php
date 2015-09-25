<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Command;


use aik099\SVNBuddy\Application;
use aik099\SVNBuddy\Command\AbstractCommand;
use aik099\SVNBuddy\DIContainer;
use Mockery as m;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\aik099\SVNBuddy\WorkingDirectoryAwareTestCase;

abstract class AbstractCommandTestCase extends WorkingDirectoryAwareTestCase
{

	/**
	 * Name of the command.
	 *
	 * @var string
	 */
	protected $commandName;

	/**
	 * Command.
	 *
	 * @var AbstractCommand
	 */
	protected $command;

	/**
	 * Command tester.
	 *
	 * @var CommandTester
	 */
	protected $commandTester;

	protected function setUp()
	{
		parent::setUp();

		$container = new DIContainer();

		$application = new Application($container);
		$this->command = $application->find($this->commandName);
		$this->commandTester = new CommandTester($this->command);
	}

	/**
	 * Runs the command and returns it's output.
	 *
	 * @param array $input   Command input.
	 * @param array $options Command tester options.
	 *
	 * @return string
	 */
	protected function runCommand(array $input = array(), array $options = array())
	{
		$input['command'] = $this->command->getName();
		$options['interactive'] = true;

		$exit_code = $this->commandTester->execute($input, $options);

		return $this->commandTester->getDisplay();
	}

}
