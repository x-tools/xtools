************
Installation
************

To install xTools, please follow these steps:

1. Download the repository into a web-accessible location.
2. Ensure that ``var/`` and all files within it (other than ``var/SymfonyRequirements.php``) are writable by the web server.
3. Run ``composer install`` and be prompted to enter database details and other configuration information.
4. Open xTools in your browser; you should see the xTools landing page.

To update the cache after making configuration changes, run ``./bin/console cache:clear``.

Single wiki
===========

To use xTools for a single wiki, set the following variables in ``parameters.yml``:

* ``app.single_wiki`` to ``true``
* ``wiki_url`` to the full URL of your wiki
* ``api_path`` to the path to the root of your wiki's API

Wiki family
===========

To use xTools for a family of wikis, set ``app.single_wiki`` to ``false`` in ``parameters.yml``.

You will also need to create a new database table to record the meta information about your wikis.
It can live wherever you want;
just set the ``database_replica_*`` variables accordingly in ``parameters.yml``.

The table must be called ``wiki`` and have the following structure:
::

    CREATE TABLE `wiki` (
        `dbname` varchar(32) NOT NULL PRIMARY KEY,
        `lang` varchar(12) NOT NULL DEFAULT 'en',
        `name` text,
        `family` text,
        `url` text
    );

(The WMF version of this table can be browsed at `Quarry #4031`_.)

.. _`Quarry #4031`: https://quarry.wmflabs.org/query/4031
