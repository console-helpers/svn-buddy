<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Process;


use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use Symfony\Component\Process\Process;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class ProcessFactoryTest extends AbstractTestCase
{

	public function testImplementsCorrectInterface()
	{
		$this->assertInstanceOf(IProcessFactory::class, new ProcessFactory());
	}

	public function testProcessCanBeCreated()
	{
		$factory = new ProcessFactory();

		$process = $factory->createProcess('command', 10);

		$this->assertInstanceOf(Process::class, $process);
		$this->assertEquals('command', $process->getCommandLine());
		$this->assertEquals(10, $process->getIdleTimeout());
		$this->assertNull($process->getTimeout());
	}

}
