The "deploy" command
====================


This command deploys changes to remote/local server.

Arguments
---------


* ``path`` - Working copy path [default: "``.``"]

Options
-------


* ``-r``, ``--remote`` - Performs remote deployment
* ``-l``, ``--local`` - Performs local deployment

Configuration settings
----------------------


* ``deploy.remote-commands`` - commands to be executed during the remote deployment (one command per line)
* ``deploy.local-commands`` - commands to be executed during the local deployment (one command per line)

Examples
--------



.. code-block:: bash

   svn-buddy.phar deploy --remote


Will perform remote deployment.


.. code-block:: bash

   svn-buddy.phar deploy --local


Will perform local deployment.
