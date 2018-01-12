.. _development:

###########
Development
###########

If you are only looking to contribute to XTools as a developer, it is recommended that you use a `Toolforge <https://wikitech.wikimedia.org/wiki/Help:Toolforge>`_ account to connect to the WMF replicas so that you'll have live data that matches production. `Requesting an account <https://wikitech.wikimedia.org/wiki/Help:Getting_Started#Toolforge_users>`_ should be the first thing you do, since it involves an approval process. Having access to Toolforge may benefit you in working on other Wikimedia projects, too.

Overview
========

- XTools is based on `Symfony 3 <https://symfony.com/doc/current/index.html>`_, which is a full MVC framework. We use `Twig <https://twig.symfony.com/doc/2.x/>`_ as our template engine.

- All the PHP lives in ``src/``.

  - There is a single `bundle <https://symfony.com/doc/current/bundles.html>`_ called ``AppBundle``, which contains the controllers, `event listeners <https://symfony.com/doc/current/event_dispatcher.html>`_, helpers and Twig extensions.

  - Models and repositories live in ``src/Xtools``. Repositories are responsible for fetching data and the models handle the logic.

- Views and assets live in ``app/Resources``.

  - In ``app/Resources/views``, there is a separate directory for each controller. The ``index.html.twig`` files are the form pages (`example <https://xtools.wmflabs.org/ec>`_), and ``result.html.twig`` pages are the result pages (`example <https://xtools.wmflabs.org/ec/en.wikipedia.org/Jimbo_Wales>`_). Some tools like the Edit Counter have multiple result pages.

- `Routes <https://symfony.com/doc/current/routing.html>`_ are configured using the ``@Route`` annotation.
- By convention, each tool has it's own controller that handles requests, instantiates a model, sets the repository, and returns it to the view. Not all the tools follow this convention, however. Each tool is also registered within ``app/config/tools.yml``.
- XTools was built to work on any MediaWiki installation, but it's target wiki farm is `Wikimedia <https://www.wikimedia.org/>`_. Some features are only available on the Wikimedia installation, which is what all the ``app.is_labs`` checks are for.

Running Development server
==========================

First make sure you meet the :ref:`prerequisites`, and then follow these steps:

1. Clone the repository: ``git clone https://github.com/x-tools/xtools.git && cd xtools``
2. Run ``composer install`` and answer all the prompts.
3. Create a new local database: ``./bin/console doctrine:database:create`` (or ``d:d:c``).
4. Run the database migrations: ``./bin/console doctrine:migrations:migrate`` (or ``d:m:m``)
5. Launch PHP's built-in server: ``./bin/console server:run`` (or ``s:r``).
6. Visit ``http://localhost:8000`` in your web browser.
7. You can stop the server with ``./bin/console server:stop`` (or ``s:s``)

The :ref:`simplecounter` is the simplest tool and should work as soon as you set up XTools.
Test it by going to http://localhost:8000/sc and put in ``Jimbo Wales`` as the Username and ``en.wikipedia.org`` as the Wiki.
After submitting you should quickly get results.

The development server does not cache application or session data; any changes you make are visible after refreshing the page.
However when you modify the ``app/config/parameters.yml`` file or other things in ``app/config``, you may need to clear the cache with ``php bin/console c:c --no-warmup``.

Assets can be dumped with ``php bin/console assetic:dump``, and if you're actively editing them you can continually watch for changes with ``php bin/console assetic:watch``.

The logs are in ``var/logs/dev.log``.
If things are acting up unexpectedly, you might try clearing the cache or restarting the server.

.. _development_wmf_replicas:

Developing against WMF replicas
===============================

If you want to use the WMF database replicas (recommended), first make sure you `have an account <https://wikitech.wikimedia.org/wiki/Help:Getting_Started#Toolforge_users>`_ and shell access. Then you can open up the necessary tunnels and a shell session with::

    ssh -L 4711:enwiki.web.db.svc.eqiad.wmflabs:3306 -L 4712:tools-db:3306 your-username@tools-dev.wmflabs.org

And set the following in ``app/config/parameters.yml``::

    app.is_labs: 1
    database_replica_host: 127.0.0.1
    database_replica_port: 4711
    database_replica_name: meta_p
    database_meta_name: meta_p
    database_replica_user: <your-db-username-here>
    database_replica_password: <your-db-password-here>
    database_toolsdb_host: 127.0.0.1
    database_toolsdb_port: 4712
    database_toolsdb_name: tools-db

Change the ``your-*-here`` bits to your own values, which you can find in your ``replica.my.cnf`` file in the home directory of your account on `Toolforge`_.

.. _Toolforge: https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database

Table mappings
==============

The replicas have `different versions of tables <https://wikitech.wikimedia.org/wiki/Help:Toolforge/Database#Tables_for_revision_or_logging_queries_involving_user_names_and_IDs>`_ that utilize indexing to improve performance. We'll want to take advantage of that.

* Go to the config directory with ``cd app/config``
* Create the file table_map.yml from the template: ``cp table_map.yml.dist table_map.yml``
* Set the contents of the file to the following::

    parameters:
        app.table.archive: 'archive_userindex'
        app.table.revision: 'revision_userindex'

For the ``logging`` table, sometimes we use ``logging_userindex`` and other times ``logging_logindex`` (depending on what we're querying for). This is handled in the code via the ``getTableName()`` method in ``Repository.php``.

Caching
=======

Caching should happen in helpers, with appropriate times-to-live.

Every helper should extend HelperBase, which has ``cacheHas()``, ``cacheGet()``, and ``cacheSave()`` methods.
These should be used in this pattern::

    public function doSomething($input)
    {
        $cacheKey = 'something.'.$input;
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }
        $something = 'big query here';
        $this->cacheSave($cacheKey, $something, 'P1D');
        return $something;
    }

The cache key can be anything, so long as it is unique within the current class
(the ``cache*()`` methods prepend the classname, so you don't have to).
The TTL syntax is from the DateInterval_ class (e.g. ``P1D`` is one day, ``PT1H`` is one hour).

The above methods are just wrappers around a PSR-6_ implementation, intended to reduce the repetition of similar lines of code.
You can, of course, retrieve the underlying CacheItemPoolInterface_ whenever you want with ``$container->get('cache.app')``.

.. _PSR-6: http://www.php-fig.org/psr/psr-6/
.. _CacheItemPoolInterface: http://www.php-fig.org/psr/psr-6/#cacheitempoolinterface
.. _DateInterval: http://php.net/manual/en/class.dateinterval.php

Style Guidelines
================

- It's called "XTools", with two capital letters.
- XTools conforms to `PSR2`_ coding standards; use ``./vendor/bin/phpcs -s .`` to check your code.
- Functions and routes must begin with the tool name.
- Version numbers follow `Semantic Versioning guidelines`_.

.. _PSR2: http://www.php-fig.org/psr/psr-2/
.. _Semantic Versioning guidelines: http://semver.org/

Tests
=====

Tests are located in the ``tests/`` directory, and match the ``src/`` directory structure. They are built with `PHPUnit <https://phpunit.de/>`_. Repositories only handle fetching data and do not need to be tested. Controllers also interact with the database, and while tests are most welcomed for these, they will not run on the continuous integration server (Travis and Scrutinizer) due to limitations.

There are also tests for linting, phpDoc blocks, and file permissions.

Use ``composer test`` to run the full suite, or ``./vendor/bin/phpunit tests/`` to run just the unit tests.

Writing the docs
================

We use ReadTheDocs. To build this documentation locally, you need ``python-sphinx`` installed,
as well as the ``sphinx_rtd_theme`` theme_.

.. _theme: https://github.com/rtfd/sphinx_rtd_theme

Then, it's simply a matter of running ``make clean && make html`` in the ``docs/`` directory,
and browsing to ``xtools/docs/_build/html/`` to view the documentation.

Documentation sections use the following (standard Python) hierarchy of section symbols:

* ``#`` with overline for parts
* ``*`` with overline for chapters
* ``=`` for sections
* ``-`` for subsections

Releases
========

Releases are made by tagging commits in the master branch. Before tagging a new release:

* Update the version numbers in ``docs/conf.py`` and ``app/config/version.yml``.
* Check the copyright year in ``README.md``, ``docs/conf.py``, and ``app/Resources/views/base.html.twig``.
* If assets were modified, bump the version number in config.yml under framework/assets/version.
* Update ``RELEASE_NOTES.md`` with any notable new information for the end user.

Then tag the release (follow the `Semantic Versioning guidelines`_, and annotate the tag with the above release notes)
and push it to GitHub.

Lastly, update the ``version`` and ``updated`` parameters at https://www.mediawiki.org/wiki/XTools

Additional Help
===============

* Email: ``tools.xtools`` @ ``tools.wmflabs.org``
* IRC: `#wikimedia-xtools <https://webchat.freenode.net/?channels=#wikimedia-xtools>`_ (`Direct link <irc://irc.freenode.net/#wikimedia-xtools>`_ - Requires an IRC client)
* MediaWiki talk page: `Talk:XTools <https://www.mediawiki.org/wiki/Talk:XTools>`_
