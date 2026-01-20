The "conflicts" (alias "cf") command
====================================


Manages recorded conflicts in a working copy.

Arguments
---------


* ``path`` - Working copy path [default: "``.``"]

Options
-------


* ``-m``, ``--mode=MODE`` - Operation mode, e.g. ``show``, ``add``, ``replace``, ``erase`` [default: "``show``"]

Configuration settings
----------------------


* ``conflicts.recorded-conflicts`` - list of conflicted paths (maintained automatically)

Examples
--------



.. code-block:: bash

   svn-buddy.phar conflicts


Shows list of recorded conflicts like this:


.. code-block:: bash

   Conflicts:
    * conflicted/path/one
    * conflicted/path/tow



.. code-block:: bash

   svn-buddy.phar conflicts --mode show


Shows list of recorded conflicts like this:


.. code-block:: bash

   Conflicts:
    * conflicted/path/one
    * conflicted/path/tow



.. code-block:: bash

   svn-buddy.phar conflicts --mode add


Adds current conflicted paths (e.g. after merge or update) to the list of recorded paths.


.. code-block:: bash

   svn-buddy.phar conflicts --mode replace


Replaced list of recorded paths with current conflicted paths (e.g. after merge or update).


.. code-block:: bash

   svn-buddy.phar conflicts --mode erase


Forgets all recorded conflicted paths.
