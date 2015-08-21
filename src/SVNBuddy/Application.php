<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace aik099\SVNBuddy;


use aik099\SVNBuddy\Command\AggregateCommand;
use aik099\SVNBuddy\Command\CleanupCommand;
use aik099\SVNBuddy\Command\CommitCommand;
use aik099\SVNBuddy\Command\CompletionCommand;
use aik099\SVNBuddy\Command\LogCommand;
use aik099\SVNBuddy\Command\MergeCommand;
use aik099\SVNBuddy\Command\ResolveCommand;
use aik099\SVNBuddy\Command\RevertCommand;
use Pimple\Container;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{

	/**
	 * Dependency injection container.
	 *
	 * @var DIContainer
	 */
	protected $dic;

	/**
	 * Creates application.
	 *
	 * @param Container $container Container.
	 */
	public function __construct(Container $container)
	{
		$this->dic = $container;

		parent::__construct('SVN-Buddy', '@git-commit@');

		$helper_set = $this->getHelperSet();
		$helper_set->set($this->dic['container_helper']);
		$helper_set->set($this->dic['date_helper']);

		set_time_limit(0);
		ini_set('memory_limit', -1);

		putenv('LC_CTYPE=en_US.UTF-8'); // For SVN.
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
		$default_commands[] = new CommitCommand();
		$default_commands[] = new AggregateCommand();
		$default_commands[] = new CompletionCommand();

		return $default_commands;
	}

}
