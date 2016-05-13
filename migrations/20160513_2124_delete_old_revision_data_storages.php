<?php
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;

return function (MigrationContext $context) {
	$container = $context->getContainer();
	$working_directory = $container['working_directory'];

	$top_level_caches = glob($working_directory . '/*.cache');

	if ( $top_level_caches ) {
		array_map('unlink', $top_level_caches);
	}

	$repository_sub_folders = glob($working_directory . '/*', GLOB_ONLYDIR);

	foreach ( $repository_sub_folders as $repository_sub_folder ) {
		$revision_caches = glob($repository_sub_folder . '/log_*.cache');

		if ( $revision_caches ) {
			array_map('unlink', $revision_caches);
		}
	}
};
