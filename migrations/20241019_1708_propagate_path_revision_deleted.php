<?php
use ConsoleHelpers\DatabaseMigration\MigrationContext;

return function (MigrationContext $context) {
	$db = $context->getDatabase();

	$sql = 'SELECT Path, RevisionDeleted
			FROM Paths
			WHERE Path LIKE "%/" AND RevisionDeleted IS NOT NULL';
	$deleted_paths = $db->fetchPairs($sql);

	if ( !$deleted_paths ) {
		return;
	}

	foreach ( $deleted_paths as $path => $revision ) {
		$sql = 'UPDATE Paths
				SET RevisionDeleted = :revision
				WHERE Path LIKE :path AND RevisionDeleted IS NULL';
		$db->perform($sql, array('revision' => $revision, 'path' => $path . '%'));
	}
};
