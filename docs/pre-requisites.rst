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

1. One or more project databases.  These should be current mediawiki installations.  The meta database should point to them.
2. A Meta database.
   If you are running more than one wiki (``app.is_single_wiki`` set to false), information on each wiki must be stored in a meta database.
   xTools uses one modeled after `The WMF Labs database. <https://wikitech.wikimedia.org/wiki/Help:MySQL_queries#meta_p_database>`_.

   This database must live on the same machine as the project databases.

   See the :ref:`installation documentation <wiki-family-installation>` for more details if you don't already have this database available.
3. An optional Tools' database, where other MediaWiki tools store their data.