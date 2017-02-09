Pre-requisites
==============

Xtools requires the following to run:

- A recent version of Linux (Windows servers are supported, however; you must enable the ``app.load_stylesheets_from_cdn`` if you want it to look nice).
- PHP 5.5.9+ (not tested on PHP7)

  - JSON must be enabled.
  - ctype needs to be enabled
  - You must have ``date.timezone`` set in ``php.ini``.
  - PDO including the driver for the database you want to use
  - Curl must be enabled.

- Composer 1.0.0+

Databases
---------

1. A main tool database.  This just needs to be created, and the xTools user needs create and destroy privilages.
2. One or more project databases.  This should be a current mediawiki install.  The meta database should point to it.

Optional Database
-----------------
If you are running more than one wiki (app.is_single_wiki set to false), information on each wiki must be stored in a meta database.  xTools uses one modeled after `The WMF Labs database. <https://wikitech.wikimedia.org/wiki/Help:MySQL_queries#meta_p_database>`_.

This database must live on the same machine as the project databases.

Run ``sql/meta.sql``  to set one up.