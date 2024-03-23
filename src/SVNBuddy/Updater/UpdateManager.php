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


use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;

class UpdateManager
{

	const NEW_VERSION_CHECK_INTERVAL = '1 day';

	/**
	 * Updater.
	 *
	 * @var Updater
	 */
	protected $updater;

	/**
	 * Config editor.
	 *
	 * @var ConfigEditor
	 */
	protected $configEditor;

	/**
	 * Process factory.
	 *
	 * @var IProcessFactory
	 */
	protected $processFactory;

	/**
	 * File, containing new version.
	 *
	 * @var string
	 */
	protected $newVersionFilename;

	/**
	 * UpdateManager constructor.
	 *
	 * @param Updater         $updater           Updater.
	 * @param ConfigEditor    $config_editor     Config editor.
	 * @param IProcessFactory $process_factory   Process factory.
	 * @param string          $working_directory Working directory.
	 */
	public function __construct(
		Updater $updater,
		ConfigEditor $config_editor,
		IProcessFactory $process_factory,
		$working_directory
	) {
		$this->updater = $updater;
		$this->configEditor = $config_editor;
		$this->processFactory = $process_factory;
		$this->newVersionFilename = $working_directory . '/new-version';
	}

	/**
	 * Sets update channel.
	 *
	 * @param string $update_channel Update channel.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When unknown update channel is given.
	 */
	public function setUpdateChannel($update_channel)
	{
		$valid_channels = array(Stability::STABLE, Stability::SNAPSHOT, Stability::PREVIEW);

		if ( !in_array($update_channel, $valid_channels) ) {
			throw new \InvalidArgumentException('Update channel "' . $update_channel . '" not found.');
		}

		$this->configEditor->set('update-channel', $update_channel);

		/** @var VersionUpdateStrategy $update_strategy */
		$update_strategy = $this->updater->getStrategy();

		if ( $update_strategy instanceof VersionUpdateStrategy ) {
			$update_strategy->setStability($update_channel);
		}
	}

	/**
	 * Returns new version.
	 *
	 * @return string
	 */
	public function getNewVersion()
	{
		if ( !file_exists($this->newVersionFilename) ) {
			$new_version = '';
			$last_checked = 0;
		}
		else {
			$new_version = file_get_contents($this->newVersionFilename);
			$last_checked = filemtime($this->newVersionFilename);
		}

		if ( $last_checked < strtotime('-' . self::NEW_VERSION_CHECK_INTERVAL) ) {
			$process = $this->processFactory->createCommandProcess('self-update', array('--check'));
			shell_exec($process->getCommandLine() . ' > /dev/null 2>&1 &');

			return '';
		}

		return $new_version;
	}

	/**
	 * Sets new version.
	 *
	 * @param string $version Version.
	 *
	 * @return void
	 */
	public function setNewVersion($version)
	{
		file_put_contents($this->newVersionFilename, $version);
	}

	/**
	 * Returns update channel.
	 *
	 * @return string
	 */
	public function getUpdateChannel()
	{
		return $this->configEditor->get('update-channel');
	}

	/**
	 * Returns updater object.
	 *
	 * @return Updater
	 */
	public function getUpdater()
	{
		return $this->updater;
	}

}
