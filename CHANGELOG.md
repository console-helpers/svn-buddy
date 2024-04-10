# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Added `-a` shortcut for the `--aggregate` option of the `merge` and `commit` commands.
- Show merged revision commit message during merge process, that is done by the `merge` command.
- Added `--exclude-bugs` and `--exclude-revisions` options to the `merge` command for better versatility.
- Added `--merges` option to the `merge` command to display only merge revisions.
- Added `--preview` option to the `merge` command to display revisions, that will be merged instead of merging them.
- Added the `changelog` command, that will show changes included in the current SVN-Buddy release.
- Added `--author` option to the `log` command to display revisions, made by a specific author.
- Added `--with-revision-url` option to the `log` command to display URL (Phabricator only for now) for each shown revision.
- Sends a beep to the Terminal, when an error occurs during any command execution.

### Changed
- Dropped support for PHP 5.4 and PHP 5.5 versions.
- The `--bugs` and `--revisions` options of the `merge` and `log` commands now can be combined for better versatility.
- Improved `merge` command phrasing for the "upcoming merge status" term. 
- Lazy load other commands to improve each individual command performance.

### Fixed
- The `--aggregate` option of the `merge` and `commit` commands wasn't working (triggered an exception) when revision without a bug wasn't present in the list of revisions.
- The revision color in merge header (that is underlined) of the `merge` command was matching color of the header itself (white+bold).
- The `log` command verifies, that bugs provided in the `--bugs` option actually exist in the repository.
- Revisions with empty 2nd line of their commit message weren't shown as "(no details)" in merge commit message. 

## [0.6.1] - 2022-12-06
### Changed
- Moved application upgrade system from Heroku into GitHub Actions.

## [0.6.0] - 2022-11-14
### Added
- Added `--aggregate` option to `merge` and `commit` commands to aggregate displayed commits by tasks.
- Added `project` command, that allows to show project meta information and reset refresh tracking regular expression.
- Added `--no-merges` option (from `log` command) to `merge` command to allow hiding merge revisions.

### Changed
- The recursive working copy lookup behavior of the `aggregate` command is disabled by default, but can be enabled via new `--recursive` option.
- Improve Phabricator integration during `merge` command by using `.arcconfig` file from the merge source repository.

### Fixed
- Attempt to print commit history of paths copied to themselves (e.g. trunk > branches/something > trunk) resulted in a recursion.
- The `--no-merges` option of `merge` command wasn't used during actual merge, but only to display to be merged revisions.
- The `Reverse-merge of ...` heading was shown even on strait merges (not reverse ones), when `commit` command was using `summary` merge template.

## [0.5.2] - 2019-06-20
### Fixed
- Fixed PHP warning, when using `commit` (`ci`) command when no merge was performed.

## [0.5.1] - 2019-05-21
### Changed
- Improve commit message of `commit` (`ci`) command by showing if path/file was copied or not.

### Fixed
- Files, that weren't changed and had no properties and were added to a changelist were displayed in `commit` (`ci`) command result.
- Empty reverse merge sub-section was always added to merge commit messages.

## [0.5.0] - 2019-03-30
### Added
- Added `group_by_bug` merge template for `ci` command, that allows grouping merged revisions by their bug ids.
- Added automatic update checker.
- Added support for display merged revision number as a link to Phabricator instance (when project has `.arcconfig` file).
- Added `--reverse` option to `merge` command allowing to perform a reverse merge.

### Changed
- Include source branch project name in commit message, when merge source project differs from merge target project.
- Default merge template for `commit` command changed from `group_by_revision` into `group_by_bug`.
- Use short inline grouping header for merge commit message, when merging single bug/revision.
- When using `--with-full-message` option with `log` and `merge` commands, then extra table separator is added between revisions to ease reading of large/wrapped commit messages.

### Fixed
- Attempt to use `--help` option on any command resulted in the exception.
- Greatly improved speed of working copy location by `aggregate` command via ignoring of known dependency folders (`node_modules` and `vendor`).
- The `svn info` command no longer fails on folders, that have `@` in their name, e.g. `node_modules/@gulp-sourcemaps`.
- Attempt to merge non-existing bugs would now result in an error (before list of unmerged revisions were shown).

## [0.4.0] - 2018-02-11
### Added
- Added `conflicts` command for manually managing list of recorded conflicts in a working copy.
- Added `--ignore-externals` option to `update` command to allow updating working copy without checking out externals.
- Added `--revision` option to `update` command to allow updating working copy to a specific revision.
- Added `--update-revision` option to `merge` command allowing to update working copy to given revision prior to merging.
- Added `--with-full-message` option to `log`, `merge` and `aggregate` commands to display non-truncated commit messages.
- Show progress bar during merging to indicate merged/total revision count.
- When using `revert` command display list of recorded conflicts before deleting it.
- Added different formats (merge templates), used during automatic merge commit log message generation.
- Added `empty` merge template allowing to prevent merge information to be used during commit log message generation.
- Added `cfg` alias to `config` command.
- Added `summary` merge template to display only summary from performed merge in a commit log message.
- Added `--merge-template` option to `commit` command to allow overriding merge template for this commit.
- Added `--record-only` option to `merge` command for marking revisions as merged without actually merging them.
- Added `search` command for finding where code was first added or last seen in a given working copy file.

### Changed
- The `update` command now also tracks conflicts resulted from problematic update.
- The list of options for `aggregate` command is now built dynamically based on options of aggregated commands.
- The `merge` command now specially ignores externals, when doing working copy update before merging.
- The `merge` command now will do update, when locally deleted files are found.
- The trailing empty lines are removed from displayed commit message for increased clarity.
- The merge heading is more readable now, because "r123" was changed into "123 revision" (e.g. `--- Merging 15512 revision into './core':`).
- The recorded conflicts are now sorted alphabetically.
- Disallow searching for whitespace-only keywords using `search` command.
- Obfuscate credentials displayed in error messages.
- While looking for "bugtraq:logregexp" property of a project look at each ref instead of using last modified only.
- Include source branch project name in commit message, when doing cross-project merge and source/target branches are named the same.

### Fixed
- Invalid merge source url was guessed for `X.0.Z` branches (e.g. `5.0.x`).
- The `aggregate` command was ignoring command aliases (e.g. `up` for `update` command).
- Externals in a working copy caused `Mixed revisions` error before merge resulting in immediate update.
- Locally deleted files in a working copy caused `Mixed revisions` error before merge resulting in immediate update.
- When `--refs` argument of `log` was used the revisions not belonging to specified refs were also shown.
- The merged revision heading (e.g. `--- Merging r15512 into './core':`) wasn't highlighted during merging.
- Added files in a working copy caused `Mixed revisions` error before merge resulting in immediate update.
- The merging heading wasn't shown, when fast network connection to Subversion server was used (command output buffer contained 2+ lines of text).
- Current revision row highlighting also affected table markup instead of just affecting text inside cells.
- Initial repository import using Subversion 1.9+ failed with `Property 'bugtraq:logregexp' not found` error.
- Attempt to use "svn-buddy" inside sub-folder of a working copy ended up in exception for Subversion 1.7+ client.
- It was possible to search for an empty keyword using "search" command.
- The externals were shown in auto-generated commit message for `commit` command.
- Deleted branches/tags of a project were introspected for a "bugtraq:logregexp" property resulting in `Path ... not found in ... revision.` error.

## [0.3.0] - 2016-09-08
### Added
- Added `--action` option to `log` command, that allows to search revisions by action (`A`, `M`, `R`, `D`) on a path within a revision.
- Added `--kind` option to `log` command, that allows to search revisions by kind (`dir` or `file`) of a path within a revision.
- Added ability to update application via new "self-update" command.
- Added `all` value to `--refs` option of `log` command to display revision from all refs in a project.
- Current working copy revision in `log` command results is now highlighted in bold.
- Added `merge.auto-commit` config setting (enabled by default), that allows to tell if commit should happen after merge.
- Added `--auto-commit` option to `merge` command to allow overriding behavior imposed by `merge.auto-commit` config setting.
- Added `--cl` option to `commit` command to allow committing changes from specified changelist only.
- Automatically put changelist name as 1st line in commit message (when `commit` command used with `--cl` option).

### Changed
- Don't remove ref from path, when showing revision paths in `log` commands's detail view.
- Attempt to view revisions of path, that never existed in a project now will exactly say that in thrown exception.
- The 4x speed improvement of `log` command, when used on a working copy.
- List of conflicts is included in commit message if they are present (before only worked for merge commits).

### Fixed
- The path `copy-from-` information wasn't stored incorrectly resulting in path shown as copied, while they weren't.
- When, in `log` command, attempting to see revisions of a particular file, that currently exists, nothing was shown.
- When, in `log` command, attempting to see revisions of a particular file, that is currently deleted, nothing was shown.
- The copied paths (during initial revision data import) where not properly associated to their projects.
- Fixed notice about "file_exists" function and "svn://" protocol, when using `merge` command or `log` command with URL instead of path.
- Outdated working copy wasn't detected during `merge` command, when `log` command was used right before it.
- Paths added to changelists weren't taken into account by `commit` command.

## [0.2.0] - 2016-05-14
### Added
- When conflicts were detected during merge, then conflicted paths would be listed in auto-generated commit message.
- The `--source-url` option of `merge` command can be specified in short form (e.g. `trunk`, `branches/branch-name`, `tags/tag-name`, `name` (for branch-to-branch or tag-to-tag merges).
- Added `--refs` option (with auto-complete) for `log` command to show revisions from ref instead of current working copy path.
- Added `log.message-limit` config setting (defaults to 68), that allows to specify optimal commit message column width.
- Added `--with-refs` option for `log` command to show refs, that revision belongs to in revision list.
- The `revert` command now not also reverts changes to paths, that are committed, but also deletes added paths.
- The `--verbose` option now also shows names of accessed cache files.
- Support for doing merges from one project into another one within same repository.
- The `log` command now displays project and ref name above displayed revision list.
- Added support for repositories where only 1 project exists and no "trunk", "branches", "tags" folders are present.

### Changed
- The `config` command now shows working copy url instead of path to stress fact, that settings are stored based on working copy url and not path.
- Wrap list of bug, associated with revision to "3 per row" to avoid too wide table creation.
- When `log` command showing revisions in detailed view, then how one bug per row to avoid table wrapping.
- The `--merge-status` option of `log` command renamed into `--with-merge-status`.
- The `--merge-oracle` option of `log` command renamed into `--with-merge-oracle`.
- The `--summary` option of `log`, 'aggregate' and `merge` commands renamed into `--with-summary`.
- The `--details` option of `log`, 'aggregate' and `merge` commands renamed into `--with-details`.
- When all revisions are displayed by `log` command display "Showing X revision(-s)" instead of "Showing X of X revision(-s)".
- The `Merged Via` column (available when `--with-merge-status` option used) of `log` command also shows refs, that merge revision belongs to.
- Improved revision path absolute-to-relative transformer and now: the project path is always cut off; the ref is cut off only for single-ref revisions.
- Wrap list of associated revision next to conflicted paths to "4 per row" to avoid too wide table creation.
- Name format of per-working copy config setting is changed, which will result in all data being lost unless migrated by hand in "~/.svn-buddy/config.json" file (old "path-settings.wc_url_hash.setting_name", new: "path-settings[wc_url].setting_name").
- Major under the hood revision information storage changes.
- The `aggregate` command no longer requires specifying `sub-command` argument, when `--ignore-*` options are used.

### Fixed
- The Subversion repositories hosted on https://unfuddle.com/ were not usable from `log` and `merge` commands.
- The output (e.g. revision query progress bar) was interfering with auto-complete (e.g. `--refs` option of `log` command).
- When showing only first line from a multi-line commit message, then `...` wasn't shown at the end to indicate, that not all commit message is displayed.
- The "," in bug list associated with a revision was colored in same color as bug itself, but it shouldn't be colored at all.
- Only first line of commit message was displayed even in detailed revision view.
- In `log` details view colored multi-line changed paths (e.g. copy operation) resulted in color affecting nearby cells.
- The "," was lost when bug list was wrapped to the next line.
- When `--refs` option of `log` command was used together with `--with-details` option the revision path were not transformed from absolute to relative.
- The Subversion repositories hosted on https://unfuddle.com/ were not usable from `commit` command.

## [0.1.0] - 2016-03-19
### Added
- Added `repository-connector.last-revision-cache-duration` config setting ("10 minutes" by default), for specifying time for how long repository should not be queried for new revisions. Set according to commit/merge frequency for your repository.

### Changed
- The last revision from repository is now cached for 10 instead of 25 minutes (helps, when doing many merged in short period of time).

### Fixed
- User config settings were lost during config upgrade process, when new default settings were added.

## [0.0.4] - 2015-12-04
### Added
- Show number of unmerged bugs (not only unmerged revisions) in `merge` command.
- The output of executed repository commands is shown, when verbosity is set to debug (-vvv).
- Added support for merging sub-folders in a working copy.
- Added `--merge-status` option for `log` command, that shows `Merged Via` column containing merge revisions affecting displayed revision.
- Show time estimated completion time, when downloading revision log info.
- Added `--merges` option for `log` command to display only merge revisions.
- Added `--no-merges` option for `log` command to display only non-merge revisions.
- Show number of displayed and total revisions in `log` command output.
- Added `--summary` option for `log` command to display change summary (how much paths were added/changed/removed) of each revision.
- The `merge` and `aggregate` commands would also forward `--summary` option to the underlying `log` command call.
- Added `--merged` and `--not-merged` options for `log` command to show merged and not yet merged revisions respectively (works based on merge commits only).
- Added `-merged-by` option for `log` command to display revisions merged by given revision(-s).

### Changed
- The `log` command will throw an exception, when given revision doesn't exist at given path.
- The inherited (from global or default value) config setting value now isn't stored.
- The format of revision log cache now will be updated automatically, when needed by `RevisionLog` class.
- Show current folder path in error about incorrect working copy folder.

### Fixed
- Attempt to edit global version of working copy setting resulted in Fatal Error.
- The `log` command with `--bugs` option was throwing exception, when bug was already merged.
- The `--ignore-add` option of `aggregate` command wasn't checking if added directory exists on disk.
- The newline symbols were stripped of string-type config setting value.
- Duplicate lines were removed from array-type config setting value.
- The `InPortalMergeSourceDetector` class wasn't working when repository url with sub-folder was given.
- Notice was emitted on missing revision log cache read attempt.
- The missing revision query progress bar was erasing all text on same line (seen on `merge` command).
- Fixed fatal error, when attempting to perform first merge on a tag.
- Attempt to set working copy config setting outside working copy was showing wrong path in the error message.
- Tree conflict during merge wasn't blocking all further merge attempts.

## [0.0.3] - 2015-09-26
### Added
- Added `update` command with `up` alias.
- Added `Config` class to allow storing per-working copy and global configuration settings.
- Added `config` command for adding/editing/deleting config settings.
- The `aggregate` command now has config setting with list of ignored directories.
- Added `--merge-oracle` option to `log` command that will show commits that might trigger a merge conflicts during `merge` command run.
- Added `--details` option to `aggregate` command, that will be passed to `merge` and `log` sub-commands.

### Changed
- The `merge` command doesn't ask user confirmation to run `svn update` when outdated/mixed revision working copy detected.
- The `log` command now reads default limit (was 10) from config setting instead of hardcoding it.
- The `merge` command now reads default merge source url from config setting, when available.

### Fixed
- The error from `which` executable was shown to end user, when no suitable editor for `commit` command were found.
- The revisions used by `merge` command were not chronologically sorted, which might have resulted in merge conflicts.
- Duplicate bug IDs were not filtered out during commit log message parsing.
- A notice was emitted, when incorrect regular expression was entered as part of config settings, that supports regular expressions.

## [0.0.2] - 2015-09-20
### Added
- Show Subversion client version as part of `svn-buddy.phar --version` command.
- Use short git commit sha in in version number.
- Add progress bar to display commit log reading progress.

### Changed
- Attempt to run `commit` command on working copy without changes now results in exception.
- Only commands implementing new `IAggregatorAwareCommand` interface can be used with `aggregate` command.

### Fixed
- The mixed revision working copies (usual thing for Subversion 1.7+) were preventing `merge` command from working.
- The unversioned files were shown in commit dialog of `commit` command.
- Added `ci` alias to `commit` command.
- Attempt to use `list` and `help` commands with `aggregate` command was useless.

## [0.0.1] - 2015-09-12
### Added
- Initial release.
- Adding `aggregate`, `cleanup`, `commit`, `log`, `merge` and `revert` commands.

[Unreleased]: https://github.com/console-helpers/svn-buddy/compare/v0.6.1...HEAD
[0.6.1]: https://github.com/console-helpers/svn-buddy/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/console-helpers/svn-buddy/compare/v0.5.2...v0.6.0
[0.5.2]: https://github.com/console-helpers/svn-buddy/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/console-helpers/svn-buddy/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/console-helpers/svn-buddy/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/console-helpers/svn-buddy/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/console-helpers/svn-buddy/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/console-helpers/svn-buddy/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/console-helpers/svn-buddy/compare/v0.0.4...v0.1.0
[0.0.4]: https://github.com/console-helpers/svn-buddy/compare/v0.0.3...v0.0.4
[0.0.3]: https://github.com/console-helpers/svn-buddy/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/console-helpers/svn-buddy/compare/v0.0.1...v0.0.2
