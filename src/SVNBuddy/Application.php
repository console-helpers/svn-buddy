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


use ConsoleHelpers\ConsoleKit\Application as BaseApplication;
use ConsoleHelpers\ConsoleKit\Container as BaseContainer;
use ConsoleHelpers\SVNBuddy\Command\AggregateCommand;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{

	/**
	 * Creates application.
	 *
	 * @param BaseContainer $container Container.
	 */
	public function __construct(BaseContainer $container)
	{
		parent::__construct($container);

		if ( $container instanceof CommandLoaderInterface ) {
			$this->setCommandLoader($container);
		}

		$helper_set = $this->getHelperSet();
		$helper_set->set($this->dic['date_helper']);
		$helper_set->set($this->dic['size_helper']);
		$helper_set->set($this->dic['output_helper']);

		set_time_limit(0);
		ini_set('memory_limit', -1);

		putenv('LC_CTYPE=en_US.UTF-8'); // For SVN.
	}

	/**
	 * @inheritDoc
	 */
	protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
	{
		try {
			return parent::doRunCommand($command, $input, $output);
		}
		catch ( \Exception $e ) {
			$this->dic['io']->notify();

			throw $e;
		}
	}

	/**
	 * Returns the long version of the application.
	 *
	 * @return string The long application version
	 *
	 * @api
	 */
	public function getLongVersion()
	{
		$version = parent::getLongVersion();

		/** @var Connector $repository_connector */
		$repository_connector = $this->dic['repository_connector'];
		$client_version = $repository_connector->getCommand('', array('--version', '--quiet'))->run();

		return $version . ' (SVN <comment>v' . trim($client_version) . '</comment>)';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultCommands()
	{
		$default_commands = parent::getDefaultCommands();

		// Add this command outside of container, because it scans other commands.
		$default_commands[] = new AggregateCommand();

		return $default_commands;
	}

}
