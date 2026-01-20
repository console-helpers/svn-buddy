# SVN-Buddy

[![CI](https://github.com/console-helpers/svn-buddy/workflows/CI/badge.svg)](https://github.com/console-helpers/svn-buddy/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/console-helpers/svn-buddy/branch/master/graph/badge.svg)](https://codecov.io/gh/console-helpers/svn-buddy)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/console-helpers/svn-buddy/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/console-helpers/svn-buddy/?branch=master)


[![Latest Stable Version](https://poser.pugx.org/console-helpers/svn-buddy/v/stable)](https://packagist.org/packages/console-helpers/svn-buddy)
[![Total Downloads](https://poser.pugx.org/console-helpers/svn-buddy/downloads)](https://packagist.org/packages/console-helpers/svn-buddy)
[![License](https://poser.pugx.org/console-helpers/svn-buddy/license)](https://packagist.org/packages/console-helpers/svn-buddy)

SVN-Buddy is a command-line tool, that was created to drastically simplify Subversion-related development tasks performed on a daily basis from command line.

The Git users will also feel right at home, because used terminology (commands/options/arguments) was inspired by Git.


## Usage

Moved to ReadTheDocs.

## Installation

1. [download](https://github.com/console-helpers/svn-buddy/releases/latest/download/svn-buddy.phar) a stable release (preferably to the folder in PATH)
2. setup auto-completion by placing `eval $(/path/to/svn-buddy.phar _completion --generate-hook -p svn-buddy.phar)` in `~/.bashrc` (Bash v4.0+ required) and reopening a Terminal window
3. (optional) switch to the `snapshot` release channel to get weekly updates by running the `/path/to/svn-buddy.phar self-update --snapshot` command
4. (optional) switch to the `preview` release channel to get daily updates by running the `/path/to/svn-buddy.phar self-update --preview` command

How to upgrade Bash on macOS: https://www.shell-tips.com/mac/upgrade-bash/ .

## Requirements

* working Subversion command-line client (was tested on v1.6, v1.7, v1.8)
* a Subversion working copy (almost all `svn-buddy.phar` commands operate inside a working copy)

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) file.

## License

SVN-Buddy is released under the BSD-3-Clause License. See the bundled [LICENSE](LICENSE) file for details.
