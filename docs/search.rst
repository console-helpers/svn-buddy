The "search" command
====================


Searches for a revision, where text was added to a file or removed from it.

Arguments
---------


* ``path`` -  File path
* ``keywords`` - Search keyword

Options
-------


* ``-t``, ``--match-type=MATCH-TYPE`` - Match type, e.g. ``first`` or ``last`` [default: "``last``"]

Examples
--------



.. code-block:: bash

   svn-buddy.phar search folder/path.php "on testMethod("


Finds where ``testMethod`` method was last seen in the ``folder/path.php`` file in the working copy.


.. code-block:: bash

   svn-buddy.phar search folder/path.php "on testMethod(" --match-type last


Finds where ``testMethod`` method was last seen in the ``folder/path.php`` file in the working copy.


.. code-block:: bash

   svn-buddy.phar search folder/path.php "on testMethod(" --match-type first


Finds where ``testMethod`` method was first added in the ``folder/path.php`` file in the working copy.
