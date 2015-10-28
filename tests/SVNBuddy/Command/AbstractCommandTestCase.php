<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Command;


use ConsoleHelpers\SVNBuddy\Application;
use ConsoleHelpers\SVNBuddy\Command\AbstractCommand;
use ConsoleHelpers\SVNBuddy\Container;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\ConsoleHelpers\ConsoleKit\WorkingDirectoryAwareTestCase;

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

		$container = new Container();

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

		$this->commandTester->execute($input, $options);

		return $this->commandTester->getDisplay();
	}

}
