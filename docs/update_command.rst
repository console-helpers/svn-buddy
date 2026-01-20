The "update" (alias "up") command
=================================

Bring changes from the repository into the working copy.

Arguments
---------

* ``path`` - Working copy path [default: ``.``]

Options
-------

* ``-r``, ``--revision=REVISION`` - Update working copy to specified revision, e.g. ``NUMBER``, ``{DATE}``, ``HEAD``, ``BASE``, ``COMMITTED``, ``PREV``
* ``--ignore-externals`` - Ignore externals definitions
* ``--auto-deploy=AUTO-DEPLOY`` - Automatically perform local deployment on successful update, e.g. ``yes`` or ``no``

Examples
--------

.. code-block:: bash

   svn-buddy.phar update

Updates a working copy.

.. code-block:: bash

   svn-buddy.phar update --revision 55

Updates a working copy to 55th revision.

.. code-block:: bash

   svn-buddy.phar update --ignore-externals

Updates a working copy, but doesn't checkout externals.

.. code-block:: bash

   svn-buddy.phar update --deploy

Perform a local deployment after update was performed or there is nothing to update.
