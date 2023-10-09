<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Command;


use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ChangelogCommand extends AbstractCommand
{

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('changelog')
			->setDescription('Displays changes included in the current SVN-Buddy release');

		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$root_path = \dirname(__DIR__) . '/../..';
		$raw_changelog = \file_get_contents($root_path . '/CHANGELOG.md');

		$markdown_renderer = Renderer::createFromMarkdown($raw_changelog);

		$this->io->writeln((string)$markdown_renderer);
	}

}
