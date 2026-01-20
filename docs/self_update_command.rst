The "self-update" command
=========================

Updates application to most recent version. Following update channels are available:

* ``stable`` (default) - new version available eventually
* ``snapshot`` - new version available on monday (only, when something was changed)
* ``preview`` - new version available once per day (only, when something was changed)

Special options exist (see examples below) to switch between update channels.

Options
-------

* ``-r``, ``--rollback`` - Revert to an older version of the application
* ``--stable`` - Force an update to the stable channel
* ``--snapshot`` - Force an update to the snapshot channel
* ``--preview`` - Force an update to the preview channel

Examples
--------

.. code-block:: bash

   svn-buddy.phar self-update

Updates to most recent version on current update channel. By default update would happen from ``stable`` channel.

.. code-block:: bash

   svn-buddy.phar self-update --rollback

In case if update was done previously allows to undo the update.

.. code-block:: bash

   svn-buddy.phar self-update --stable

Change current update channel to ``stable`` and immediately performs the update.

.. code-block:: bash

   svn-buddy.phar self-update --snapshot

Change current update channel to ``snapshot`` and immediately performs the update.

.. code-block:: bash

   svn-buddy.phar self-update --preview

Change current update channel to ``preview`` and immediately performs the update.
