******************
Welcome to xTools!
******************


Xtools is a system of tools written by `User:X! on Wikipedia <http://en.wikipedia.org/wiki/User:Soxred93>`_.  The original version lives at
`http://tools.wmflabs.org/xtools <http://tools.wmflabs.org/xtools>`_

.. toctree::
    :maxdepth: 2
    :numbered:
    :titlesonly:

    installation
    configuration
    development

Pre-requisites
--------------
Xtools requires the following to run:

- A recent version of Linux (Windows servers are not supported).
- PHP 5.5.9

  - JSON must be enabled.
  - ctype needs to be enabled
  - You must have ``date.timezone`` set in ``php.ini``.
  - PDO including the driver for the database you want to use

- Composer 1.0.0 

Xtools must be run in the server root, running in a sub-directory is not supported. 

Databases
---------

1. A main tool database.  This just needs to be created, and the xTools user needs create and destroy privilages.
2. A meta database.  This should contain one table, "wiki", which is modeled after `The WMF Labs one <https://wikitech.wikimedia.org/wiki/Help:MySQL_queries#meta_p_database>`_.  There is a sample database at ``sql/meta.sql``.
3. One or more project databases.  This should be a current mediawiki install.  The meta database should point to it.

Help
----
For help on xTools installation, there are several places you can ask.

* `Github <http://github.com/x-Tools/xtools/issues/new>`_
* `Phabricator <https://phabricator.wikimedia.org/maniphest/task/create/?project=Tool-labs-tools-xtools>`_
* `IRC <https://webchat.freenode.net/?channels=#wikimedia-xtools>`_ (`Direct link <irc://irc.freenode.net/#wikimedia-xtools>`_ - Requires an IRC client)