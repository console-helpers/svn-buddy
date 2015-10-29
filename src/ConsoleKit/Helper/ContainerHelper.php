<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/console-kit
 */

namespace ConsoleHelpers\ConsoleKit\Helper;


use ConsoleHelpers\ConsoleKit\Container;
use Symfony\Component\Console\Helper\Helper;

class ContainerHelper extends Helper
{

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private $_container;

	/**
	 * Creates helper instance.
	 *
	 * @param Container $container Container.
	 */
	public function __construct(Container $container)
	{
		$this->_container = $container;
	}

	/**
	 * Returns container.
	 *
	 * @return Container
	 */
	public function getContainer()
	{
		return $this->_container;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return 'container';
	}

}
