<?php

use ConsoleHelpers\DatabaseMigration\MigrationContext;
use ConsoleHelpers\SVNBuddy\Repository\RevisionLog\MigrationContext as SvnBuddyMigrationContext;

return function (MigrationContext $context) {
	if ( !$context instanceof SvnBuddyMigrationContext ) {
		return;
	}

	$db = $context->getDatabase();

	// Get commits, that belong to multiple projects.
	$sql = 'SELECT cp.Revision, GROUP_CONCAT(cp.ProjectId)
			FROM CommitProjects cp
			JOIN Commits c ON c.Revision = cp.Revision
			GROUP BY cp.Revision
			HAVING COUNT(*) > 1';
	$multi_project_commits = $db->fetchPairs($sql);

	if ( !$multi_project_commits ) {
		return;
	}

	$to_reparse = array();
	$revision_log = $context->getrevisionLog();

	$revision_count = count($multi_project_commits);
	echo sprintf('Found %d commits with multiple projects.', $revision_count) . PHP_EOL;

	foreach ( $multi_project_commits as $revision => $project_ids ) {
		echo 'Processing ' . $revision . ' revision... ';

		$sql = 'SELECT Id, Path
				FROM Projects
				WHERE Id IN (:project_ids)';
		$project_paths = $db->fetchPairs($sql, array('project_ids' => explode(',', $project_ids)));

		$log = $revision_log
			->getCommand(
				'log',
				array('--revision', $revision, '--xml', '--verbose', '{repository_url}')
			)
			->run();

		$projects_found = array();

		// Determine the actual project, where the commit belongs based on its paths.
		foreach ( $log->logentry->paths->path as $path ) {
			foreach ( $project_paths as $project_id => $project_path ) {
				if ( strpos($path, $project_path) === 0 ) {
					$projects_found[$project_id] = true;
					break;
				}
			}
		}

		// Leave only projects that were found in the SVN repository.
		foreach ( array_keys($projects_found) as $project_id ) {
			unset($project_paths[$project_id]);
		}

		if ( !$project_paths ) {
			echo 'Skipped.' . PHP_EOL;
			continue;
		}

		// Remove incorrect associations.
		foreach ( $project_paths as $project_id => $project_path ) {
			$sql = 'SELECT pa.Id
					FROM CommitPaths cp
					JOIN Paths pa ON pa.Id = cp.PathId
					WHERE cp.Revision = :revision AND pa.ProjectPath = :project_path';
			$path_id = $db->fetchValue($sql, array('revision' => $revision, 'project_path' => $project_path));

			$sql = 'DELETE FROM CommitPaths
					WHERE Revision = :revision AND PathId = :path_id';
			$db->perform($sql, array('revision' => $revision, 'path_id' => $path_id));

			$sql = 'DELETE FROM CommitProjects
					WHERE Revision = :revision AND ProjectId = :project_id';
			$db->perform($sql, array('revision' => $revision, 'project_id' => $project_id));
		}

		echo 'Fixed.' . PHP_EOL;
		$to_reparse[] = $revision;
	}

	foreach ( array_chunk($to_reparse, 10) as $to_reparse_chunk ) {
		echo sprintf(
			'Run the "%s reparse -r %s" command.' . PHP_EOL,
			reset($_SERVER['argv']),
			implode(',', $to_reparse_chunk)
		);
	}
};
