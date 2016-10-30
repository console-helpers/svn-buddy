<?php
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\DatabaseMigration\MigrationContext;
use ConsoleHelpers\SVNBuddy\Command\ConflictsCommand;

return function (MigrationContext $context) {
	$container = $context->getContainer();

	/** @var ConfigEditor $config_editor */
	$config_editor = $container['config_editor'];

	$old_suffix = '.merge.recent-conflicts';
	$new_suffix = '.' . ConflictsCommand::SETTING_CONFLICTS_RECORDED_CONFLICTS;

	foreach ( $config_editor->getAll() as $setting_name => $setting_value ) {
		if ( strpos($setting_name, $old_suffix) !== false ) {
			$config_editor->set($setting_name, null);
			$config_editor->set(str_replace($old_suffix, $new_suffix, $setting_name), $setting_value);
		}
	}
};
