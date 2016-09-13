<?php
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\DatabaseMigration\MigrationContext;

return function (MigrationContext $context) {
	$container = $context->getContainer();

	/** @var ConfigEditor $config_editor */
	$config_editor = $container['config_editor'];

	foreach ( $config_editor->getAll() as $setting_name => $setting_value ) {
		if ( substr($setting_name, 0, 14) === 'path-settings.' ) {
			$config_editor->set($setting_name, null);
		}
	}
};
