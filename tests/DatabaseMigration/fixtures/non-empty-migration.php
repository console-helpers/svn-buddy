<?php
use ConsoleHelpers\DatabaseMigration\MigrationContext;

return function (MigrationContext $context) {
	$context->getDatabase()->perform('test');
};
