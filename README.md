# SVN-Buddy

[![Build Status](https://travis-ci.org/console-helpers/svn-buddy.svg?branch=master)](https://travis-ci.org/console-helpers/svn-buddy)
[![Coverage Status](https://coveralls.io/repos/console-helpers/svn-buddy/badge.svg?branch=master&service=github)](https://coveralls.io/github/console-helpers/svn-buddy?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/console-helpers/svn-buddy/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/console-helpers/svn-buddy/?branch=master)


[![Latest Stable Version](https://poser.pugx.org/console-helpers/svn-buddy/v/stable)](https://packagist.org/packages/console-helpers/svn-buddy)
[![Total Downloads](https://poser.pugx.org/console-helpers/svn-buddy/downloads)](https://packagist.org/packages/console-helpers/svn-buddy)
[![License](https://poser.pugx.org/console-helpers/svn-buddy/license)](https://packagist.org/packages/console-helpers/svn-buddy)

SVN-Buddy is a command-line tool, that was created to drastically simplify Subversion-related development tasks performed on a daily basis from command line.

The Git users will also feel right at home, because used terminology (commands/options/arguments) was inspired by Git.

## Usage

Almost each of commands described below accepts `path` argument to specify working copy path. This allows to work with several working copies without the need to `cd` to each of them.

### The "config" command

This command allows to change configuration settings, that are used by other commands.

Some of the commands (`merge`, `log` and `aggregate`) also use central data store (located in `~/.svn-buddy/config.json` file) to store information about a working copy.

* If nothing is stored for a given working copy, then a global default would be used.
* If global default is missing, then built-in default would be used.
* Both working copy and global settings are configurable.

The association between setting values and a working copy is done using working copy url. Such approach allows to preserve setting values, when working copy is moved to different location on disk.

#### Arguments
 * `path` - Working copy path [default: "`.`"]

#### Options
 * `-s`, `--show=SETTING` - Shows only given (instead of all) setting value
 * `-e`, `--edit=SETTING` - Change setting value in the Interactive Editor
 * `-d`, `--delete=SETTING` - Delete setting
 * `-g`, `--global` - Operate on global instead of working copy-specific settings

#### Examples

Feel free to add `--global` option to any of examples below to operate on global settings instead of working copy ones.

```
svn-buddy.phar config
```

Shows values of all settings in current working copy:

![all working copy settings](docs/images/SvnBuddy_ConfigCommand_ShowAllSettings.png)

```
svn-buddy.phar config --show merge.source-url
```

Shows value of a `merge.source-url` setting:

![single working copy setting](docs/images/SvnBuddy_ConfigCommand_ShowSingleSetting.png)

```
svn-buddy.phar config --edit merge.source-url
```

Change value of a `merge.source-url` setting using interactive editor.

```
svn-buddy.phar config --delete merge.source-url
```

Delete a `merge.source-url` setting.

### The "log" command

This command shows the log messages for a set of revisions.

What makes this command really shine (compared to `svn log` command) is:

* speed - the revision information from repository is cached locally and therefore it's accessed blazingly fast
* revision search - revisions can not only be found by path and number, but also using ref (e.g. `trunk` or `tags/stable`) and a bug number
* revision filtering - when found revisions can be filtered further by their merge status (merged, not merged, is merge revision by itself)
* detailed revision information - different information about revision is available and can be shown using set of built-in views: compact view, summary view, detailed view, merge conflict prediction view, merge status view

The revision is considered merged only, when associated merge revision can be found. Unfortunately Subversion doesn't create merge revisions on direct path operations (e.g. replacing `tags/stable` with `trunk`) and therefore affected revisions won't be considered as merged when using this command.

Bugs, associated with each revision, are determined by parsing [bugtraq:logregex](https://tortoisesvn.net/docs/release/TortoiseSVN_sk/tsvn-dug-bugtracker.html) Subversion property at the root folder of last changed ref in a project. The assumption is made, that Subversion project won't move to a different issue tracker and therefore value of `bugtraq:logregex` Subversion property is cached forever.

#### Arguments

* `path` - Working copy path or URL [default: "`.`"]

#### Options

* `-r`, `--revisions=REVISIONS` - List of revision(-s) and/or revision range(-s), e.g. `53324`, `1224-4433`
* `-b`, `--bugs=BUGS` - List of bug(-s), e.g. `JRA-1234`, `43644`
* `--refs=REFS` - List of refs, e.g. `trunk`, `branches/branch-name`, `tags/tag-name`
* `--merges` - Show merge revisions only
* `--no-merges` - Hide merge revisions
* `--merged` - Shows only revisions, that were merged at least once
* `--not-merged` - Print only not merged revisions
* `--merged-by=MERGED-BY` - Show revisions merged by list of revision(-s) and/or revision range(-s)
* `--action=ACTION` - Show revisions, whose paths were affected by specified action, e.g. `A`, `M`, `R`, `D`
* `--kind=KIND` - Show revisions, whose paths match specified kind, e.g. `dir` or `file`
* `-d`, `--with-details` - Shows detailed revision information, e.g. paths affected
* `-s`, `--with-summary` - Shows number of added/changed/removed paths in the revision
* `--with-refs` -  Shows revision refs
* `--with-merge-oracle` - Shows number of paths in the revision, that can cause conflict upon merging
* `--with-merge-status` - Shows merge revisions affecting this revision
* `--max-count=MAX-COUNT` - Limit the number of revisions to output

#### Configuration settings

* `log.limit` - maximal number of displayed revisions (defaults to `10`)
* `log.message-limit` - maximal width (in symbols) of `Log Message` column (defaults to `68`)
* `log.merge-conflict-regexps` - list of regular expressions for path matching inside revisions ( used to predict merge conflicts, when `--with-merge-oracle` option is used)

#### Examples

By default ref (e.g. `trunk`) to display revisions for is detected from current working copy. To avoid need to keep different working copies around add `--refs X` (e.g. `--refs branches/branch-name`) to display revisions from specified ref(-s) of a project.

All `--with-...` options can be combined together to create more complex views.

```
svn-buddy.phar log
```

Displays all revisions without filtering:

![default log view](docs/images/SvnBuddy_LogCommand.png)

* the number of displayed revisions is always limited to value of `log.limit` configuration setting
* the total number revisions is also displayed (when relevant) to indicate how much more revisions aren't shown

```
svn-buddy.phar log --max-count 5
```

Displays all revisions without filtering, but limited by `--max-count` option value.

```
svn-buddy.phar log --revisions 10,7,34-36,3
```

Displays `3`, `7`, `10`, `34`, `35` and `36` revisions. Any combination of revision(-s)/revision range(-s) can be used.

```
svn-buddy.phar log --bugs JRA-10,6443
```

Displays revisions associated with `JRA-10` and `6443` bugs.

```
svn-buddy.phar log --refs branches/5.2.x,releases/5.2.1
```

Displays revisions retrieved from `branches/5.2.x` and `releases/5.2.1` refs. The valid refs formats are:

* `trunk`
* `branches/branch-name`
* `tags/tag-name`
* `releases/release-name`

```
svn-buddy.phar log --action D
```

Displays revisions, where at least one path (directory or file) was deleted.

```
svn-buddy.phar log --kind dir
```

Displays revisions, where at least one affected path was a directory.

```
svn-buddy.phar log --action D --kind dir
```

Displays revisions, where at least one affected path was a directory at least one path (directory or file) was deleted, but this isn't guaranteed to be the same path.

```
svn-buddy.phar log --merges
```

Displays only merge revisions.

```
svn-buddy.phar log --no-merges
```

Displays all, but merge revisions.

```
svn-buddy.phar log --merged
```

Displays only merged revisions.

```
svn-buddy.phar log --not-merged
```

Displays all, but merged revisions.

```
svn-buddy.phar log --merged-by 12,15-17
```

* Displays revisions merged by `12`, `15`, `16` and `17` revisions.

```
svn-buddy.phar log --with-details
```

Displays detailed information about revisions:

![detailed log view](docs/images/SvnBuddy_LogCommand_WithDetails.png)

```
svn-buddy.phar log --with-summary
```

Compact alternative to `--with-details` option where only totals about made changes in revisions are shown in separate column:

![summary log view](docs/images/SvnBuddy_LogCommand_WithSummary.png)

```
svn-buddy.phar log --with-refs
```

Displays refs, affected by each revision:

![refs log view](docs/images/SvnBuddy_LogCommand_WithRefs.png)

```
svn-buddy.phar log --with-merge-oracle
```

Shows how much paths in each revision can (but not necessarily will) cause conflicts when will be merged:

![merge oracle log view](docs/images/SvnBuddy_LogCommand_WithMergeOracle.png)

The `log.merge-conflict-regexps` configuration setting needs to be specified before Merge Oracle can be used. This can be done using following commands:

* globally: `svn-buddy.phar config --edit log.merge-conflict-regexps --global`
* in a working copy: `svn-buddy.phar config --edit log.merge-conflict-regexps`

Once Interactive Editor opens enter regular expression (one per line) used to match a path (e.g. `#/composer\\.lock$#` matches to a `composer.lock` file in any sub-folder).

In above image there are 2 revisions and each of them contain 1 path (detected using configuration setting defined above) that might result in a conflict.

```
svn-buddy.phar log --with-merge-oracle --with-details
```

Displays changed paths in each revision, but also highlights potentially conflicting paths (from merge oracle) with red:

![merge oracle & details log view](docs/images/SvnBuddy_LogCommand_WithMergeOracle_WithDetails.png)

```
svn-buddy.phar log --with-merge-status
```

For each displayed revision also displays revision responsible for merging it (with merge revision ref):

![merge status log view](docs/images/SvnBuddy_LogCommand_WithMergeStatus.png)

### The "merge" command

This command merges changes from another project or ref within same project into a working copy.

The merges performed outside of SVN-Buddy are detected automatically (thanks to `svn mergeinfo` being used internally).

#### Arguments

* `path` - Working copy path [default: "`.`"]

#### Options

* `--source-url=SOURCE-URL` - Merge source url (absolute or relative) or ref name, e.g. `branches/branch-name`
* `-r`, `--revisions=REVISIONS` - List of revision(-s) and/or revision range(-s) to merge, e.g. `53324`, `1224-4433` or `all`
* `-b`, `--bugs=BUGS` - List of bug(-s) to merge, e.g. `JRA-1234`, `43644`
* `-d`, `--with-details` - Shows detailed revision information, e.g. paths affected
* `-s`, `--with-summary` - Shows number of added/changed/removed paths in the revision

#### Configuration settings

* `merge.source-url` - the default url to merge changes from
* `merge.recent-conflicts` - list of conflicted paths (maintained automatically)

#### Examples

The `--source-url` option can be used with any of below examples to merge from that url instead of guessing it. The source url can be specified using:

* absolute url: `svn://domain.com/path/to/project/branches/branch-name`
* relative url: `^/path/to/project/branches/branch-name`
* ref: `branches/branch-name` or `tags/tag-name` or `trunk`

```
svn-buddy.phar merge
```

The above command does following:

1. only, when `--source-url` option isn't used
 * detects merge source url
 * when not detected ask to use `--source-url` option to specify it manually
 * when detected, then store it into `merge.source-url` configuration setting
2. determines unmerged revisions between working copy and detected merge source url
3. displays the results (no merge is performed):
 * number of unmerged revisions/bugs
 * status of previous merge operation
 * list of unmerged revisions

![merge status](docs/images/SvnBuddy_MergeCommand.png)

```
svn-buddy.phar merge --revisions 5,3,77-79
```

Does all of above and attempts to merge specified revisions into a working copy. As the merge progresses the output of `svn merge` command is shown back to user in real-time (no buffering).

Revisions are merged one by one, because:

* the results are displayed back to user much faster, then when merging several revisions in one go
* conflict resolution is much easier, because exact revision that caused conflict is already known

The specified revisions to be merged are automatically sorted chronologically.

![merge revisions](docs/images/SvnBuddy_MergeCommand_Revisions.png)

Only 2 outcomes from above command execution are possible:

* the merge was successful - nothing extra, except `svn merge` command output is displayed
* the conflict happened during the merge or conflicting path existed prior the merge - the above shown error screen is displayed and conflicted paths are stored in `merge.recent-conflicts` configuration setting

The error screen shows:

* the `svn merge` command output (if was executed)
* the list of conflicted paths
* for each path the list of non-merged revisions prior to merged one, that are affecting the conflicted path are displayed

The last bit can be quite helpful, when for example merging revision that changes a file, but not merging one before it, where this file was created.

After the merge conflict was solved the same merge command can be re-run without need to remove already merged revisions from `--revisions` option value.

Can't be used together with `--bugs` option.

```
svn-buddy.phar merge --revisions all
```

Will merge all non-merged revisions.

```
svn-buddy.phar merge --bugs JRA-4343,3453
```

Will merge all revisions, that are associated with `JRA-4343` and `3453` bugs. This would be a major time saver in cases, when:

* only bug number is known
* bug consists of multiple revisions created on different days

Can't be used together with `--revisions` option.

```
svn-buddy.phar merge --with-details
```

Thanks to `log` command being used behind the scenes to display non-merged revisions it's possible to forward `--with-details` option to it to see paths affected by each non-merged revision.


```
svn-buddy.phar merge --with-summary
```

Thanks to `log` command being used behind the scenes to display non-merged revisions it's possible to forward `--with-summary` option to it to see totals for paths affected by each non-merged revision.

### The "commit" (alias "ci") command

The command sends changes from your working copy to the repository.

#### Arguments

* `path` - Working copy path [default: "`.`"]

#### Examples

```
svn-buddy.phar commit
```

The command workflow is following:

1. abort automatically, when
 * non-resolved conflicts are present
 * if no paths are changed
2. open an Interactive Editor for commit message entry
3. when committing result of a merge the **commit message is auto-generated** from:
 * commit messages of all merged revisions
 * list of conflicted paths (if any)
4. once user is done changing commit message a confirmation dialog is shown to ensure user really wants to perform commit
5. when user agreed previously the commit is made

The auto-generated commit message looks like this:

```
Merging from Trunk to Stable
* r22758: message line 1
message line 2
message line 3
message line 4
* r22796:  message line 1
message line 2
message line 3

Conflicts:
  * path/to/conflicted-file
```

Description:

* `Trunk` is folder name of merge source url
* `Stable` is folder name of merge target (working copy)
* `22758` and `22796` are merged revisions
* `message line ...` are lines from commit message of merged revisions

### The "cleanup" command

Recursively clean up the working copy, removing locks, resuming unfinished operations, etc.

#### Arguments

* `path` - Working copy path [default: "`.`"]

#### Examples

```
svn-buddy.phar cleanup
```

### The "revert" command

Restore pristine working copy file (undo most local edits).

#### Arguments

* `path` - Working copy path [default: "`.`"]

#### Examples

```
svn-buddy.phar revert
```

### The "update" (alias "up") command

Bring changes from the repository into the working copy.

#### Arguments

* `path` - Working copy path [default: "`.`"]

#### Examples

```
svn-buddy.phar update
```

### The "aggregate" command

Runs other command sequentially on every working copy on a path.

#### Arguments

* `sub-command` - Command to execute on each found working copy
* `path` - Path to folder with working copies [default: "`.`"]

#### Options

* `-d`, `--with-details` - Shows detailed revision information, e.g. paths affected
* `-s`, `--with-summary` - Shows number of added/changed/removed paths in the revision
* `--ignore-add=IGNORE-ADD` - Adds path to ignored directory list
* `--ignore-remove=IGNORE-REMOVE` - Removes path to ignored directory list
* `--ignore-show` - Show ignored directory list

#### Configuration settings

* `aggregate.ignore` - list of paths ignored by `aggregate` command, when searching for working copies

#### Examples

```
svn-buddy.phar aggregate --ignore-add some-path
```

Adds `some-path` path (can be relative or absolute) to ignored path list.

```
svn-buddy.phar aggregate --ignore-remove some-path
```

Removes `some-path` path (can be relative or absolute) from ignored path list.

```
svn-buddy.phar aggregate --ignore-show
```

Shows list of ignored paths.

### The "list" command

Displays available commands. The version of Subversion binary is also displayed next to application version.

#### Examples

```
svn-buddy.phar
svn-buddy.phar list
```

### The "help" command

Displays help for a command.

## Installation

1. download latest released PHAR file from https://github.com/console-helpers/svn-buddy/releases page.
2. setup auto-completion by placing `eval $(/path/to/svn-buddy.phar _completion --generate-hook -p svn-buddy.phar)` in `~/.bashrc`

## Requirements

* working Subversion command-line client (was tested on v1.6, v1.7, v1.8)
* a Subversion working copy (almost all `svn-buddy.phar` commands operate inside a working copy)

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) file.

## License

SVN-Buddy is released under the BSD-3-Clause License. See the bundled [LICENSE](LICENSE) file for details.
