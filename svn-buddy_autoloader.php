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


use Aura\Sql\Profiler\ProfilerInterface;

if ( \class_exists('ConsoleHelpers\SVNBuddy\Autoload', false) === false ) {

	/**
	 * Custom autoloader.
	 */
	final class Autoload
	{

		/**
		 * Loads a class.
		 *
		 * @param string $class_name The name of the class to load.
		 *
		 * @return boolean
		 */
		public static function load($class_name)
		{
			// Only load classes belonging to this library.
			if ( \stripos($class_name, 'ConsoleHelpers\SVNBuddy\Database\TStatementProfiler') !== 0 ) {
				return false;
			}

			$method = new \ReflectionMethod(ProfilerInterface::class, 'setActive');
			$method_parameters = $method->getParameters();

			if ( PHP_VERSION_ID >= 70000 && $method_parameters[0]->hasType() ) {
				require_once __DIR__ . '/src/SVNBuddy/Database/TStatementProfiler7.php';
			}
			else {
				require_once __DIR__ . '/src/SVNBuddy/Database/TStatementProfiler5.php';
			}

			return true;
		}

	}

	\spl_autoload_register(__NAMESPACE__ . '\Autoload::load');
}
