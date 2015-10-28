<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Process;


use aik099\SVNBuddy\Process\ProcessFactory;

class ProcessFactoryTest extends \PHPUnit_Framework_TestCase
{

	public function testImplementsCorrectInterface()
	{
		$this->assertInstanceOf('aik099\\SVNBuddy\\Process\\IProcessFactory', new ProcessFactory());
	}

	public function testProcessCanBeCreated()
	{
		$factory = new ProcessFactory();

		$process = $factory->createProcess('command', 10);

		$this->assertInstanceOf('Symfony\\Component\\Process\\Process', $process);
		$this->assertEquals('command', $process->getCommandLine());
		$this->assertEquals(10, $process->getIdleTimeout());
		$this->assertNull($process->getTimeout());
	}

}
