The "reparse" command
=====================

Reparses given revision.

Arguments
---------

* ``path`` - Working copy path [default: ``.``]


Options
-------

* ``-r``, ``--revisions=REVISIONS`` - List of revision(-s) and/or revision range(-s) to reparse, e.g. ``53324``, ``1224-4433`` or ``all``


Examples
--------

.. code-block:: bash

   svn-buddy.phar reparse --revisions 12345

Re-reads and reparses 12345 revision information.
