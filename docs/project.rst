The "project" command
=====================


Changes and displays project configuration.

Arguments
---------


* ``path`` - Working copy path [default: "``.``"]


Options
-------


* ``--refresh-bug-tracking`` - Refreshes value of "bugtraq:logregex" SVN property of the project
* ``--show-meta`` - Shows meta information of a project

Examples
--------



.. code-block:: bash

   svn-buddy.phar project --refresh-bug-tracking


Pulls ``bugtraq:logregex`` SVN property from recently modified trunk/branch/tag and stores into project configuration.


.. code-block:: bash

   svn-buddy.phar project --show-meta


Displays project meta information in following format:


.. image:: images/SvnBuddy_ProjectCommand_ShowMetaOption.png
   :alt: project meta information

