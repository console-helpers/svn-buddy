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


use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\FileDownloader;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;

class VersionUpdateStrategy implements StrategyInterface
{

	const RELEASES_URL = 'https://raw.githubusercontent.com/console-helpers/svn-buddy-updater/master/releases.json';

	/**
	 * Stability.
	 *
	 * @var string
	 */
	protected $stability = Stability::STABLE;

	/**
	 * Local version.
	 *
	 * @var string
	 */
	protected $localVersion;

	/**
	 * Url, from where remote version can be downloaded.
	 *
	 * @var string
	 */
	protected $remoteUrl;

	/**
	 * Sets stability.
	 *
	 * @param string $stability Stability.
	 *
	 * @return void
	 */
	public function setStability($stability)
	{
		$this->stability = $stability;
	}

	/**
	 * Set version string of the local phar
	 *
	 * @param string $version Version.
	 *
	 * @return void
	 */
	public function setCurrentLocalVersion($version)
	{
		$this->localVersion = $version;
	}

	/**
	 * Download the remote Phar file.
	 *
	 * @param Updater $updater Updater.
	 *
	 * @return void
	 * @throws \LogicException When there is nothing to download.
	 */
	public function download(Updater $updater)
	{
		if ( !$this->remoteUrl ) {
			throw new \LogicException('Run "hasUpdate()" on updater prior to downloading new version.');
		}

		file_put_contents($updater->getTempPharFile(), $this->downloadFile($this->remoteUrl));
	}

	/**
	 * Retrieve the current version available remotely.
	 *
	 * @param Updater $updater Updater.
	 *
	 * @return string
	 * @throws \LogicException When update channel doesn't exist.
	 */
	public function getCurrentRemoteVersion(Updater $updater)
	{
		$this->remoteUrl = '';
		$releases = json_decode(
			$this->downloadFile(self::RELEASES_URL),
			true
		);

		if ( !isset($releases[$this->stability]) ) {
			throw new \LogicException('The "' . $this->stability . '" update channel not found.');
		}

		$version = key($releases[$this->stability]);
		$this->remoteUrl = $releases[$this->stability][$version]['phar_download_url'];

		return $version;
	}

	/**
	 * Retrieve the current version of the local phar file.
	 *
	 * @param Updater $updater Updater.
	 *
	 * @return string
	 */
	public function getCurrentLocalVersion(Updater $updater)
	{
		return $this->localVersion;
	}

	/**
	 * Downloads file securely.
	 *
	 * @param string $url Url.
	 *
	 * @return string
	 * @throws HttpRequestException When failed to download a file.
	 */
	protected function downloadFile($url)
	{
		// If not for "CN_match" on PHP < 5.6 just "humbug_get_contents" function could be used.
		$file_downloader = new FileDownloader();
		$result = $file_downloader->download($url);

		if ( false === $result ) {
			throw new HttpRequestException(sprintf('Request to URL failed: %s', $url));
		}

		return $result;
	}

}
