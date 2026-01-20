The "commit" (alias "ci") command
=================================

The command sends changes from your working copy to the repository.

Arguments
---------

* ``path`` - Working copy path [default: ``.``]

Options
-------

* ``--cl`` - Operate only on members of selected changelist
* ``--merge-template=MERGE-TEMPLATE`` - Use alternative merge template for this commit
* ``--auto-deploy=AUTO-DEPLOY`` - Automatically perform remote deployment on successful commit, e.g. ``yes`` or ``no``

Configuration settings
----------------------

* ``commit.merge-template`` - log message template for merge commits (defaults to ``group_by_revision``)

Examples
--------

.. code-block:: bash

   svn-buddy.phar commit


The command workflow is following:

1. abort automatically, when

   * non-resolved conflicts are present
   * no paths are changed

2. open an Interactive Editor for commit message entry

3. commit message is automatically generated, from:

   * selected changelist name (when ``--cl`` option was used)
   * merged revision information using selected merge template (when this is a merge commit)
   * list of conflicted paths (if conflicts were present, but later were resolved)

4. once user is done changing commit message a confirmation dialog is shown to ensure user really wants to perform commit

5. when user agreed previously the commit is made

The auto-generated commit message looks like this (with ``group_by_revision`` merge template):


.. code-block:: bash

   Changelist Name
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


Description:

* ``Trunk`` is folder name of merge source url
* ``Stable`` is folder name of merge target (working copy)
* ``22758`` and ``22796`` are merged revisions
* ``message line ...`` are lines from commit message of merged revisions


.. code-block:: bash

   svn-buddy.phar commit --cl


Same as above, but will also:

* ask user to select changelist
* put changelist name in commit message


.. code-block:: bash

   svn-buddy.phar commit --merge-template summary


Same as above, but will use ``summary`` merge template instead of merge template configured for this working copy.


.. code-block:: bash

   svn-buddy.phar commit --deploy


Perform a remote deployment after commit was performed or there is nothing to commit.

