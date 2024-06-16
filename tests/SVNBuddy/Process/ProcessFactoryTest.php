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


use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;
use ConsoleHelpers\SVNBuddy\Process\ProcessFactory;
use Symfony\Component\Process\Process;
use Tests\ConsoleHelpers\SVNBuddy\AbstractTestCase;

class ProcessFactoryTest extends AbstractTestCase
{

	/**
	 * Process factory
	 *
	 * @var ProcessFactory
	 */
	protected $processFactory;

	/**
	 * @before
	 */
	protected function setUpTest()
	{
		$this->processFactory = new ProcessFactory();
	}

	public function testImplementsCorrectInterface()
	{
		$this->assertInstanceOf(IProcessFactory::class, $this->processFactory);
	}

	public function testCreateProcess()
	{
		$process = $this->processFactory->createProcess(array('command', 'arg with space', 'arg-without-space'), 10);

		$this->assertInstanceOf(Process::class, $process);
		$this->assertEquals("'command' 'arg with space' 'arg-without-space'", $process->getCommandLine());
		$this->assertEquals(10, $process->getIdleTimeout());
		$this->assertNull($process->getTimeout());
	}

	public function testCreateCommandProcess()
	{
		$process = $this->processFactory->createCommandProcess('command', array('arg with space', 'arg-without-space'));

		$this->assertInstanceOf(Process::class, $process);
		$this->assertStringMatchesFormat(
			"'%sphp%s' '%s' 'command' 'arg with space' 'arg-without-space'",
			$process->getCommandLine()
		);
	}

}
