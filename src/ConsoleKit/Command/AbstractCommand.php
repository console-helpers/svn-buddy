<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/console-kit
 */

namespace ConsoleHelpers\ConsoleKit\Command;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\ConsoleKit\Helper\ContainerHelper;
use ConsoleHelpers\ConsoleKit\Container;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class.
 */
abstract class AbstractCommand extends Command implements CompletionAwareInterface
{

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	protected $io;

	/**
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		// Don't use IO from container, because it contains outer IO which doesn't reflect sub-command calls.
		$this->io = new ConsoleIO($input, $output, $this->getHelperSet());

		$this->prepareDependencies();
	}

	/**
	 * Return possible values for the named option
	 *
	 * @param string            $optionName Option name.
	 * @param CompletionContext $context    Completion context.
	 *
	 * @return array
	 */
	public function completeOptionValues($optionName, CompletionContext $context)
	{
		$this->prepareDependencies();

		return array();
	}

	/**
	 * Return possible values for the named argument
	 *
	 * @param string            $argumentName Argument name.
	 * @param CompletionContext $context      Completion context.
	 *
	 * @return array
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context)
	{
		$this->prepareDependencies();

		return array();
	}

	/**
	 * Prepare dependencies.
	 *
	 * @return void
	 */
	protected function prepareDependencies()
	{

	}

	/**
	 * Runs another command.
	 *
	 * @param string $name      Command name.
	 * @param array  $arguments Arguments.
	 *
	 * @return integer
	 */
	protected function runOtherCommand($name, array $arguments = array())
	{
		$arguments['command'] = $name;
		$cleanup_command = $this->getApplication()->find($name);

		$input = new ArrayInput($arguments);

		return $cleanup_command->run($input, $this->io->getOutput());
	}

	/**
	 * Returns container.
	 *
	 * @return Container
	 */
	protected function getContainer()
	{
		static $container;

		if ( !isset($container) ) {
			/** @var ContainerHelper $container_helper */
			$container_helper = $this->getHelper('container');

			$container = $container_helper->getContainer();
		}

		return $container;
	}

}
