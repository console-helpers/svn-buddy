# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
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

[Unreleased]: https://github.com/console-helpers/svn-buddy/compare/v0.0.3...HEAD
[0.0.3]: https://github.com/console-helpers/svn-buddy/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/console-helpers/svn-buddy/compare/v0.0.1...v0.0.2
