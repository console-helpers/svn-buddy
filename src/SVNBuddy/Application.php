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
use ConsoleHelpers\SVNBuddy\Command\CleanupCommand;
use ConsoleHelpers\SVNBuddy\Command\CommitCommand;
use ConsoleHelpers\SVNBuddy\Command\CompletionCommand;
use ConsoleHelpers\SVNBuddy\Command\ConfigCommand;
use ConsoleHelpers\SVNBuddy\Command\LogCommand;
use ConsoleHelpers\SVNBuddy\Command\MergeCommand;
use ConsoleHelpers\SVNBuddy\Command\RevertCommand;
use ConsoleHelpers\SVNBuddy\Command\UpdateCommand;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

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

		$helper_set = $this->getHelperSet();
		$helper_set->set($this->dic['date_helper']);

		set_time_limit(0);
		ini_set('memory_limit', -1);

		putenv('LC_CTYPE=en_US.UTF-8'); // For SVN.
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
		$client_version = $repository_connector->getCommand('', '--version --quiet')->run();

		return $version . ' (SVN <comment>v' . trim($client_version) . '</comment>)';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultCommands()
	{
		$default_commands = parent::getDefaultCommands();

		$default_commands[] = new MergeCommand();
		// $default_commands[] = new ResolveCommand();
		$default_commands[] = new CleanupCommand();
		$default_commands[] = new RevertCommand();
		$default_commands[] = new LogCommand();
		$default_commands[] = new UpdateCommand();
		$default_commands[] = new CommitCommand();
		$default_commands[] = new AggregateCommand();
		$default_commands[] = new CompletionCommand();
		$default_commands[] = new ConfigCommand();

		return $default_commands;
	}

}
