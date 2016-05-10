<?php
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;

return function (MigrationContext $context) {
	$context->getDatabase()->perform('test');
};
