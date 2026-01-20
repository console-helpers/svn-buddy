The "aggregate" command
=======================

Runs other command sequentially on every working copy on a path. Almost all other commands can be used in such a manner.

Arguments
---------

* ``sub-command`` - Command to execute on each found working copy
* ``path`` - Path to folder with working copies [default: ``.``]

Own options
-----------

* ``--ignore-add=IGNORE-ADD`` - Adds path to ignored directory list
* ``--ignore-remove=IGNORE-REMOVE`` - Removes path to ignored directory list
* ``--ignore-show`` - Show ignored directory list

Aggregated command options
--------------------------

* ``-d``, ``--with-details`` - Shows detailed revision information, e.g. paths affected
* ``-s``, ``--with-summary`` - Shows number of added/changed/removed paths in the revision
* ``--merges`` - Show merge revisions only
* ``--no-merges`` - Hide merge revisions
* ``--merged`` - Shows only revisions, that were merged at least once
* ``--not-merged`` - Shows only revisions, that were not merged
* ``--action=ACTION`` - Show revisions, whose paths were affected by specified action, e.g. ``A``, ``M``, ``R``, ``D``
* ``--kind=KIND`` - Show revisions, whose paths match specified kind, e.g. ``dir`` or ``file``
* ``--author=AUTHOR`` - Show revisions, made by a given author
* ``-f``, ``--with-full-message`` - Shows non-truncated commit messages
* ``--with-refs`` - Shows revision refs
* ``--with-merge-oracle`` - Shows number of paths in the revision, that can cause conflict upon merging
* ``--with-merge-status`` - Shows merge revisions affecting this revision
* ``--max-count=MAX-COUNT`` - Limit the number of revisions to output
* ``--ignore-externals`` - Ignore externals definitions
* ``--refresh-bug-tracking`` - Refreshes value of "bugtraq:logregex" SVN property of the project
* ``--show-meta`` - Shows meta information of a project

Configuration settings
----------------------

* ``aggregate.ignore`` - list of paths ignored by ``aggregate`` command, when searching for working copies

Examples
--------

.. code-block:: bash

   svn-buddy.phar aggregate --ignore-add some-path


Adds ``some-path`` path (can be relative or absolute) to ignored path list.


.. code-block:: bash

   svn-buddy.phar aggregate --ignore-remove some-path


Removes ``some-path`` path (can be relative or absolute) from ignored path list.


.. code-block:: bash

   svn-buddy.phar aggregate --ignore-show


Shows list of ignored paths.
