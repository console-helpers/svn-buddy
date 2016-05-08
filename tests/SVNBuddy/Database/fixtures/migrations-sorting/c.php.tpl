<?php
use ConsoleHelpers\SVNBuddy\Database\MigrationManagerContext;

return function (MigrationManagerContext $context) {
	$sql = 'INSERT INTO SampleTable (Title)
			VALUES (:title);';

	$context->getDatabase()->perform($sql, array('title' => 'Test 2'));
};
