.. _installation:

############
Installation
############

.. warning::
    XTools was originally designed to work on any MediaWiki installation. However, after years of little to no interest
    from third party wikis, support for anything other than the Wikimedia farm of wikis has stalled to prevent maintenance burden.
    If your third party wiki is interested in using XTools, please contact us at `mw:Talk:XTools <https://www.mediawiki.org/wiki/Talk:XTools>`_
    or `create a Phabricator ticket <https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?projects=XTools>`_.

For contributors, see :ref:`development` for additional, more detailed instructions specific to setting up a local development environment. The prerequisites listed below still apply.

.. _prerequisites:

Prerequisites
=============

XTools requires the following to run:

- PHP 7.2 or newer, including:

  - A MySQL-like database, and PDO including the driver for the database you want to use (e.g. `PDO_MYSQL <https://secure.php.net/manual/en/ref.pdo-mysql.php>`_.
  - `cURL <https://secure.php.net/manual/en/curl.setup.php>`_ must be enabled. On some environments you may need to enable this in your php.ini file. Look for a line like ``;extension=php_curl.dll`` and uncomment it by removing the leading ``;``.
  - Additional PHP extensions are also required, as specified in composer.json.

- `Composer <https://getcomposer.org/>`_
- `Node <https://nodejs.org/en/>`_ (tested with version specified by .nvmrc) and `npm <https://www.npmjs.com/>`_.

Instructions
============

1. Download the `latest release <https://github.com/x-tools/xtools/releases>`_ into a web-accessible location. For contributors, you should develop off of the `master <https://github.com/x-tools/xtools>`_ branch.
2. Ensure that ``var/`` and all files within it (other than ``var/SymfonyRequirements.php``) are writable by the web server.
3. Run ``composer install``. You will be prompted to enter database details and other configuration information. See :ref:`configuration` for documentation on each parameter.
4. Optionally, create the XTools database: ``php bin/console doctrine:database:create`` and run the migrations with ``php bin/console doctrine:migrations:migrate``. This is actually only used for usage statistics (e.g. see `xtools.wmflabs.org/meta <https://xtools.wmflabs.org/meta>`_). XTools will run without it but doing so may cause silent failures, as the requests to record usage are made with AJAX.
5. Compile the assets with ``./node_modules/.bin/encore production`` (or `dev` for development).
6. With each deployment or pull from master, you may need to clear the cache. Use ``php bin/console cache:clear --no-warmup`` to clear the cache. For a production environment be sure to append ``--env=prod`` to these commands. You must also clear the cache whenever you make configuration changes.

In production, you may find that further server-level configuration is needed. The setup process for Wikimedia Cloud VPS (which runs on Debian Buster) is documented on `Wikitech <https://wikitech.wikimedia.org/wiki/Tool:XTools#Production>`_. This may be of assistance when installing XTools on similar Linux distributions.

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
