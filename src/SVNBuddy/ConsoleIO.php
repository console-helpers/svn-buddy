<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy;


use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ConsoleIO
{

	/**
	 * Input.
	 *
	 * @var InputInterface
	 */
	private $_input;

	/**
	 * Output.
	 *
	 * @var OutputInterface
	 */
	private $_output;

	/**
	 * Helper set.
	 *
	 * @var HelperSet
	 */
	private $_helperSet;

	/**
	 * Creates class instance.
	 *
	 * @param InputInterface  $input      Input.
	 * @param OutputInterface $output     Output.
	 * @param HelperSet       $helper_set Helper set.
	 */
	public function __construct(InputInterface $input, OutputInterface $output, HelperSet $helper_set)
	{
		$this->_input = $input;
		$this->_output = $output;
		$this->_helperSet = $helper_set;
	}

	/**
	 * Gets argument by name.
	 *
	 * @param string $name The name of the argument.
	 *
	 * @return mixed
	 */
	public function getArgument($name)
	{
		return $this->_input->getArgument($name);
	}

	/**
	 * Gets an option by name.
	 *
	 * @param string $name The name of the option.
	 *
	 * @return mixed
	 */
	public function getOption($name)
	{
		return $this->_input->getOption($name);
	}

	/**
	 * Is this input means interactive?
	 *
	 * @return boolean
	 */
	public function isInteractive()
	{
		return $this->_input->isInteractive();
	}

	/**
	 * Gets the decorated flag.
	 *
	 * @return boolean true if the output will decorate messages, false otherwise
	 */
	public function isDecorated()
	{
		return $this->_output->isDecorated();
	}

	/**
	 * Determines if verbose output is being requested.
	 *
	 * @return boolean
	 */
	public function isVerbose()
	{
		return $this->_output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
	}

	/**
	 * Determines if very verbose output is being requested.
	 *
	 * @return boolean
	 */
	public function isVeryVerbose()
	{
		return $this->_output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
	}

	/**
	 * Determines if debug output is being requested.
	 *
	 * @return boolean
	 */
	public function isDebug()
	{
		return $this->_output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
	}

	/**
	 * Writes a message to the output.
	 *
	 * @param string|array $messages The message as an array of lines or a single string.
	 * @param boolean      $newline  Whether to add a newline.
	 * @param integer      $type     The type of output (one of the OUTPUT constants).
	 *
	 * @return void
	 */
	public function write($messages, $newline = false, $type = OutputInterface::OUTPUT_NORMAL)
	{
		$this->_output->write($messages, $newline, $type);
	}

	/**
	 * Writes a message to the output and adds a newline at the end.
	 *
	 * @param string|array $messages The message as an array of lines of a single string.
	 * @param integer      $type     The type of output (one of the OUTPUT constants).
	 *
	 * @return void
	 */
	public function writeln($messages, $type = OutputInterface::OUTPUT_NORMAL)
	{
		$this->_output->writeln($messages, $type);
	}

	/**
	 * Asks a confirmation to the user.
	 * The question will be asked until the user answers by nothing, yes, or no.
	 *
	 * @param string|array $question The question to ask.
	 * @param boolean      $default  The default answer if the user enters nothing.
	 *
	 * @return boolean true if the user has confirmed, false otherwise
	 */
	public function askConfirmation($question, $default = true)
	{
		/** @var QuestionHelper $helper */
		$helper = $this->_helperSet->get('question');
		$confirmation_question = new ConfirmationQuestion(
			'<question>' . $question . ' [' . ($default ? 'y' : 'n') . ']?</question> ',
			$default
		);

		return $helper->ask($this->_input, $this->_output, $confirmation_question);
	}

	/**
	 * Asks user to choose.
	 *
	 * @param string $question      The question to ask.
	 * @param array  $options       Valid answer options.
	 * @param mixed  $default       Default answer.
	 * @param string $error_message Error on incorrect answer.
	 *
	 * @return mixed
	 */
	public function choose($question, array $options, $default, $error_message)
	{
		/** @var QuestionHelper $helper */
		$helper = $this->_helperSet->get('question');
		$choice_question = new ChoiceQuestion('<question>' . $question . '</question> ', $options, $default);
		$choice_question->setErrorMessage($error_message);

		return $helper->ask($this->_input, $this->_output, $choice_question);
	}

	/**
	 * Returns progress bar instance.
	 *
	 * @param integer $max Maximum steps (0 if unknown).
	 *
	 * @return ProgressBar
	 */
	public function createProgressBar($max = 0)
	{
		return new ProgressBar($this->_output, $max);
	}

	/**
	 * Returns output.
	 *
	 * @return OutputInterface
	 */
	public function getOutput()
	{
		return $this->_output;
	}

}
