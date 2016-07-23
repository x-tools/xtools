********************************
Welcome to the Rewrite of Xtools
********************************


Xtools is a system of tools written by `User:X! on Wikipedia <http://en.wikipedia.org/wiki/User:Soxred93>`_.  The original version lives at
`http://tools.wmflabs.org/xtools <http://tools.wmflabs.org/xtools>`_

.. toctree::
    :maxdepth: 2
    :numbered:
    :titlesonly:

    installation
    configuration

Pre-requisites
--------------
Xtools requires the following to run:

* PHP 5.x.x TODO: Figure out minimum Symfony version
* MySQL X.X.X TODO: Figure out minimum Symfony version
* Composer 1.0.0 TODO: Figure out minimum Symfony version

Databases
---------

#. A main tool database.  This just needs to be created, and the xTools user needs create and destroy privilages.
#. A meta database.  This should contain one table, "wiki", which is modeled after `The WMF Labs one <https://wikitech.wikimedia.org/wiki/Help:MySQL_queries#meta_p_database>`_.  There is a sample database at ``sql/meta.sql``.
#. One or more project databases.  This should be a current mediawiki install.  The meta database should point to it.

Help
----
For help on xTools installation, there are several places you can ask.

* `Github <http://github.com/x-Tools/xtools/issues/new>`_
* `Phabricator <https://phabricator.wikimedia.org/maniphest/task/create/?project=Tool-labs-tools-xtools>`_ TODO: Check url
* `IRC <https://webchat.freenode.net/?channels=#wikimedia-xtools>`_ (`Direct link <irc://irc.freenode.net/#wikimedia-xtools>`_ - Requires an IRC client)