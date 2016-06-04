<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Updater;


use Humbug\SelfUpdate\Updater as BaseUpdater;
use Humbug\SelfUpdate\VersionParser;

class Updater extends BaseUpdater
{

	/**
	 * Detects if new versions are available.
	 *
	 * @return boolean
	 */
	protected function newVersionAvailable()
	{
		$this->newVersion = $this->strategy->getCurrentRemoteVersion($this);
		$this->oldVersion = $this->strategy->getCurrentLocalVersion($this);

		list ($new_stability, $new_version) = $this->parseStability($this->newVersion);
		list ($old_stability, $old_version) = $this->parseStability($this->oldVersion);

		// Release with different stability a newer one.
		if ( $new_stability !== $old_stability ) {
			return true;
		}

		list ($new_version, $new_offset) = $this->parseOffset($new_version);
		list ($old_version, $old_offset) = $this->parseOffset($old_version);

		// Release made with larger offset is a newer one.
		if ( $new_version === $old_version ) {
			return $new_offset > $old_offset;
		}

		// Just see which version is larger.
		$version_parser = new VersionParser(array($new_version, $old_version));

		return $version_parser->getMostRecentAll() === $new_version;
	}

	/**
	 * Returns version parsed into stability and actual version.
	 *
	 * @param string $version Version.
	 *
	 * @return array
	 */
	protected function parseStability($version)
	{
		$parts = explode(':', $version);

		if ( count($parts) === 1 ) {
			return array(Stability::STABLE, $parts[0]);
		}

		return $parts;
	}

	/**
	 * Returns version parsed into tag and commit count after that tag.
	 *
	 * @param string $version Version.
	 *
	 * @return array
	 */
	protected function parseOffset($version)
	{
		if ( preg_match('/^(.*)-([\d]+)-g.{7}$/', $version, $regs) ) {
			return array($regs[1], $regs[2]);
		};

		return array($version, 0);
	}

}
