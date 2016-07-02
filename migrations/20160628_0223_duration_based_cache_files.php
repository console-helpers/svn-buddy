<?php
use ConsoleHelpers\DatabaseMigration\MigrationContext;
use ConsoleHelpers\SVNBuddy\Cache\FileCacheStorage;

if ( !class_exists('Migration20160628_0223', false) ) {
	class Migration20160628_0223
	{

		public function __invoke(MigrationContext $context)
		{
			$container = $context->getContainer();
			$working_directory = $container['working_directory'];

			$repository_sub_folders = glob($working_directory . '/*', GLOB_ONLYDIR);

			foreach ( $repository_sub_folders as $repository_sub_folder ) {
				$old_cache_files = glob($repository_sub_folder . '/*.cache');
				$new_cache_files = glob($repository_sub_folder . '/*_D*.cache');

				$cache_files = array_diff($old_cache_files, $new_cache_files);

				foreach ( $cache_files as $cache_file ) {
					$cache = new FileCacheStorage($cache_file);
					$cache_content = $cache->get();

					$cache_expires_on = $cache_content['expiration'];

					if ( !isset($cache_expires_on) ) {
						$cache_content['duration'] = null;
					}
					else {
						$cache_content['duration'] = $this->getDateDiff(filemtime($cache_file), $cache_expires_on);
					}

					$cache->set($cache_content);

					$filename_suffix = 'D' . (isset($cache_content['duration']) ? $cache_content['duration'] : 'INF');
					$new_cache_file = str_replace('.cache', '_' . $filename_suffix . '.cache', $cache_file);

					if ( file_exists($new_cache_file) ) {
						unlink($cache_file);
					}
					else {
						rename($cache_file, $new_cache_file);
					}
				}
			}
		}

		protected function getDateDiff($from_timestamp, $to_timestamp, $is_fix = false)
		{
			static $duration_mapping = array(
				'y' => 'years',
				'm' => 'months',
				'd' => 'days',
				'h' => 'hours',
				'i' => 'minutes',
				's' => 'seconds',
			);

			$from_date = new DateTime('@' . $from_timestamp);
			$to_date = new DateTime('@' . $to_timestamp);
			$interval = $to_date->diff($from_date);

			$diff_string = array();

			foreach ( $duration_mapping as $interval_property => $duration_name ) {
				if ( $interval->$interval_property > 0 ) {
					$diff_string[] = '+' . $interval->$interval_property . ' ' . $duration_name;
				}
			}

			if ( count($diff_string) !== 1 ) {
				if ( $is_fix ) {
					throw new LogicException('Cache duration is too complex. Unable to migrate.');
				}
				else {
					return $this->getDateDiff($from_timestamp, $to_timestamp + 1, true);
				}
			}

			$now = time();

			return strtotime(implode(' ', $diff_string), $now) - $now;
		}

	}
}

return new Migration20160628_0223();
