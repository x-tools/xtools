.. _installation:

############
Installation
############

For contributors, see :ref:`development` for additional, more detailed instructions specific to setting up a local development environment. The prerequisites listed below still apply.

.. _prerequisites:

Prerequisites
=============

XTools requires the following to run:

- A recent version of Linux or Unix (such as MacOS). Windows servers are supported, however; you must enable the ``app.load_stylesheets_from_cdn`` if you want it to look nice.
- PHP 5.6 or newer. If you have done PHP development on your machine before, you might already have these popular libraries installed:

  - `JSON <https://secure.php.net/manual/en/json.setup.php>`_ must be enabled.
  - `ctype <https://secure.php.net/manual/en/ctype.setup.php>`_ needs to be enabled.
  - A MySQL-like database, and PDO including the driver for the database you want to use (e.g. `PDO_MYSQL <https://secure.php.net/manual/en/ref.pdo-mysql.php>`_.
  - `cURL <https://secure.php.net/manual/en/curl.setup.php>`_ must be enabled. On some environments you may need to enable this in your php.ini file. Look for a line like ``;extension=php_curl.dll`` and uncomment it by removing the leading ``;``.

- `Composer <https://getcomposer.org/>`_ 1.0.0+
- `Node <https://nodejs.org/en/>`_ and `npm <https://www.npmjs.com/>`_ (tested with versions 6.2.1+ and 3.9.3+, respectively).

Instructions
============

1. Download the `latest release <https://github.com/x-tools/xtools/releases>`_ into a web-accessible location. For contributors, you should develop off of the `master <https://github.com/x-tools/xtools>`_ branch.
2. Ensure that ``var/`` and all files within it (other than ``var/SymfonyRequirements.php``) are writable by the web server.
3. Run ``composer install``. You will be prompted to enter database details and other configuration information. See :ref:`configuration` for documentation on each parameter.
4. Create the XTools database: ``php bin/console doctrine:database:create`` and run the migrations with ``php bin/console doctrine:migrations:migrate``. This is actually only used for usage statistics (e.g. see `xtools.wmflabs.org/meta <https://xtools.wmflabs.org/meta>`_). XTools will run without it but doing so may cause silent failures, as the requests to record usage are made with AJAX.
5. With each deployment or pull from master, you may need to dump the assets and clear the cache. Use ``php bin/console assetic:dump`` to generate the CSS and JS, and ``php bin/console cache:clear` to clear the cache. For a production environment be sure to append ``--env=prod`` to these commands. You must also clear the cache whenever you make configuration changes.

In production, you may find that further server-level configuration is needed. The setup process for Wikimedia Cloud VPS (which runs on Debian Jessie) is documented on `Wikitech <https://wikitech.wikimedia.org/wiki/Tool:XTools#Production>`_. This may be of assistance when installing XTools on similar Linux distributions.

Single wiki
===========

If you are running XTools against a single wiki, make sure you using the following :ref:`configuration options <configuration>`:

* ``app.single_wiki`` to ``true``
* ``wiki_url`` to the full URL of your wiki.
* ``api_path`` to the path to the root of your wiki's API.

.. _wiki-family-installation:

Wiki family
===========

To use XTools for a family of wikis, set ``app.single_wiki`` to ``false`` in ``parameters.yml``.

You will also need a database table that contains meta information about your wikis. It can live wherever you want; just set the ``database_replica_*`` variables accordingly in ``parameters.yml``. XTools was built for one resembling the `WMF database <https://wikitech.wikimedia.org/wiki/Help:MySQL_queries#meta_p_database>`_.

The table must be called ``wiki`` and have the following structure:
::

    CREATE TABLE `wiki` (
        `dbname` varchar(32) NOT NULL PRIMARY KEY,
        `lang` varchar(12) NOT NULL DEFAULT 'en',
        `name` text,
        `family` text,
        `url` text
    );

The WMF version of this table can be browsed at `Quarry #4031`_.

.. _`Quarry #4031`: https://quarry.wmflabs.org/query/4031
