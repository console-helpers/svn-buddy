# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Added `--action` option to `log` command, that allows to search revisions by action (`A`, `M`, `R`, `D`) on a path within a revision.
- Added `--kind` option to `log` command, that allows to search revisions by kind (`dir` or `file`) of a path within a revision.

### Changed
...

### Fixed
...

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
- The `log` command now reads default limit (was 10) from config setting instead of harcoding it.
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

[Unreleased]: https://github.com/console-helpers/svn-buddy/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/console-helpers/svn-buddy/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/console-helpers/svn-buddy/compare/v0.0.4...v0.1.0
[0.0.4]: https://github.com/console-helpers/svn-buddy/compare/v0.0.3...v0.0.4
[0.0.3]: https://github.com/console-helpers/svn-buddy/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/console-helpers/svn-buddy/compare/v0.0.1...v0.0.2
