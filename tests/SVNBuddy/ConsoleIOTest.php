<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy;


use aik099\SVNBuddy\ConsoleIO;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ConsoleIOTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Input interface.
	 *
	 * @var ObjectProphecy
	 */
	protected $input;

	/**
	 * Output interface.
	 *
	 * @var ObjectProphecy
	 */
	protected $output;

	/**
	 * Helper set.
	 *
	 * @var ObjectProphecy
	 */
	protected $helperSet;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	protected $io;

	protected function setUp()
	{
		parent::setUp();

		$this->input = $this->prophesize('Symfony\\Component\\Console\\Input\\InputInterface');
		$this->output = $this->prophesize('Symfony\\Component\\Console\\Output\\OutputInterface');
		$this->helperSet = $this->prophesize('Symfony\\Component\\Console\\Helper\\HelperSet');

		$this->io = new ConsoleIO($this->input->reveal(), $this->output->reveal(), $this->helperSet->reveal());
	}

	public function testGetArgument()
	{
		$this->input->getArgument('name')->willReturn('OK')->shouldBeCalled();

		$this->assertEquals('OK', $this->io->getArgument('name'));
	}

	public function testGetOption()
	{
		$this->input->getOption('name')->willReturn('OK')->shouldBeCalled();

		$this->assertEquals('OK', $this->io->getOption('name'));
	}

	public function testIsInteractive()
	{
		$this->input->isInteractive()->willReturn(true)->shouldBeCalled();

		$this->assertTrue($this->io->isInteractive());
	}

	public function testIsDecorated()
	{
		$this->output->isDecorated()->willReturn(true)->shouldBeCalled();

		$this->assertTrue($this->io->isDecorated());
	}

	/**
	 * @dataProvider outputVerbosityDataProvider
	 */
	public function testIsVerbose($verbosity_level)
	{
		$this->output->getVerbosity()->willReturn($verbosity_level)->shouldBeCalled();
		$actual = $this->io->isVerbose();

		if ( $verbosity_level >= OutputInterface::VERBOSITY_VERBOSE ) {
			$this->assertTrue($actual, 'Output should be verbose.');
		}
		else {
			$this->assertFalse($actual, 'Output should not be verbose.');
		}
	}

	/**
	 * @dataProvider outputVerbosityDataProvider
	 */
	public function testIsVeryVerbose($verbosity_level)
	{
		$this->output->getVerbosity()->willReturn($verbosity_level)->shouldBeCalled();
		$actual = $this->io->isVeryVerbose();

		if ( $verbosity_level >= OutputInterface::VERBOSITY_VERY_VERBOSE ) {
			$this->assertTrue($actual, 'Output should be very verbose.');
		}
		else {
			$this->assertFalse($actual, 'Output should not be very verbose.');
		}
	}

	/**
	 * @dataProvider outputVerbosityDataProvider
	 */
	public function testIsDebug($verbosity_level)
	{
		$this->output->getVerbosity()->willReturn($verbosity_level)->shouldBeCalled();
		$actual = $this->io->isDebug();

		if ( $verbosity_level >= OutputInterface::VERBOSITY_DEBUG ) {
			$this->assertTrue($actual, 'Output should be debug.');
		}
		else {
			$this->assertFalse($actual, 'Output should not be debug.');
		}
	}

	public function outputVerbosityDataProvider()
	{
		return array(
			'quiet' => array(OutputInterface::VERBOSITY_QUIET),
		    'normal' => array(OutputInterface::VERBOSITY_NORMAL),
		    'verbose' => array(OutputInterface::VERBOSITY_VERBOSE),
		    'very verbose' => array(OutputInterface::VERBOSITY_VERY_VERBOSE),
		    'debug' => array(OutputInterface::VERBOSITY_DEBUG),
		);
	}

	public function testWrite()
	{
		$this->output->write('text', true, OutputInterface::OUTPUT_NORMAL)->shouldBeCalled();

		$this->io->write('text', true, OutputInterface::OUTPUT_NORMAL);
	}

	public function testWriteln()
	{
		$this->output->writeln('text', OutputInterface::OUTPUT_NORMAL)->shouldBeCalled();

		$this->io->writeln('text', OutputInterface::OUTPUT_NORMAL);
	}

	/**
	 * @dataProvider askConfirmationDataProvider
	 */
	public function testAskConfirmation($answer)
	{
		$question_helper = $this->prophesize('Symfony\\Component\\Console\\Helper\\QuestionHelper');
		$this->helperSet->get('question')->willReturn($question_helper)->shouldBeCalled();

		$question_helper
			->ask(
				$this->input->reveal(),
				$this->output->reveal(),
				Argument::that(function ($question) use ($answer) {
					$default_text = $answer ? 'y' : 'n';

					return $question instanceof ConfirmationQuestion
						&& $question->getQuestion() === '<question>text [' . $default_text . ']?</question> '
						&& $question->getDefault() === $answer;
				})
			)
			->shouldBeCalled();

		$this->io->askConfirmation('text', $answer);
	}

	public function askConfirmationDataProvider()
	{
		return array(
			array(true),
			array(false),
		);
	}

	public function testChoose()
	{
		$question_helper = $this->prophesize('Symfony\\Component\\Console\\Helper\\QuestionHelper');
		$this->helperSet->get('question')->willReturn($question_helper)->shouldBeCalled();

		$question_helper
			->ask(
				$this->input->reveal(),
				$this->output->reveal(),
				Argument::that(function ($question) {
					// TODO: The proper error message isn't tested.
					return $question instanceof ChoiceQuestion
						&& $question->getQuestion() === '<question>text</question> '
						&& $question->getChoices() === array('option_1', 'option_2')
						&& $question->getDefault() === 'option_2';
				})
			)
			->shouldBeCalled();

		$this->io->choose('text', array('option_1', 'option_2'), 'option_2', 'error msg');
	}

	public function testCreateProgressBar()
	{
		$progress_bar = $this->io->createProgressBar(10);

		$this->assertInstanceOf('Symfony\\Component\\Console\\Helper\\ProgressBar', $progress_bar);
		$this->assertEquals(10, $progress_bar->getMaxSteps());
	}

	public function testGetOutput()
	{
		$this->assertSame($this->output->reveal(), $this->io->getOutput());
	}

}
