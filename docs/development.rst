###########
Development
###########

To contribute to the development of XTools, you may fork us on GitHub.  A few things to be aware of first:

1. XTools is based on Symfony 3. We use Twig as our template engine.  Symfony is a full MVC system.
   a. The controllers are located at ``src/AppBundle/controller``.  They are sorted by tool.
   b. The twig templates are located at ``app/resources/views``.  They are sorted by tool.
2. We use the ``@Route`` syntax to configure routes. 
3. Every tool requires a twig directory and one controller. Also, core parts of XTools require the tool to be registered within `app/config/tools.yml`.

Style Guidelines
================

- It's called "XTools", with two capital letters.
- XTools conforms to `PSR2`_ coding standards; use ``./vendor/bin/phpcs`` to check your code.
- Functions and routes must begin with the tool name.
- Version numbers follow `Semantic Versioning guidelines`_.

.. _PSR2: http://www.php-fig.org/psr/psr-2/
.. _Semantic Versioning guidelines: http://semver.org/

Running Development server
==========================

First make sure you meet the :ref:`pre-requisites`, and then follow these steps:

1. Clone the repository: ``git clone https://github.com/x-tools/xtools-rebirth.git && cd xtools-rebirth``
2. Run ``composer install`` and answer all the prompts.
3. Create a new local database: ``./bin/console doctrine:database:create`` (or ``d:d:c``).
4. Run the database migrations: ``./bin/console doctrine:migrations:migrate`` (or ``d:m:m``)
5. Launch PHP's built-in server: ``./bin/console server:run`` (or ``s:r``).
6. Visit ``http://localhost:8000`` in your web browser.
7. You can stop the server with ``./bin/console server:stop`` (or ``s:s``)

The :ref:`simplecounter` is the simplest tool and should work as soon as you set up XTools.
Test it by going to http://localhost:8000/sc and put in ``Jimbo Wales`` as the Username and ``en.wikipedia.org`` as the Wiki.
After submitting you should quickly get results.

The development server does not cache data; any changes you make are visible after refreshing the page.
When you edit the ``app/config/parameters.yml`` file, you'll need to clear the cache with ``./bin/console c:c``.

Assets can be dumped with ``./bin/console assetic:dump``,
and if you're actively editing them you can continually watch for changes with ``./bin/console assetic:watch``.

The logs are in ``var/logs/dev.log``.
If things are acting up unexpectedly, you might try clearing the cache or restarting the server.

Developing against WMF databases
================================

If you want to use the WMF database replicas, open two tunnels with::

    ssh -L 4711:enwiki.labsdb:3306 tools-login.wmflabs.org -N -l your-username-here
    ssh -L 4712:tools.labsdb:3306 tools-login.wmflabs.org -N -l your-username-here

And set the following in ``app/config/parameters.yml``::

    app.is_labs: 1
    database_replica_host: 127.0.0.1
    database_replica_port: 4711
    database_replica_name: meta_p
    database_meta_name: meta_p
    database_replica_user: your-uxxxx-username-here
    database_replica_password: your-password-here
    database_toolsdb_host: 127.0.0.1
    database_toolsdb_port: 4712
    database_toolsdb_name: toollabs_p

(Change the ``your-*-here`` bits to your own values,
which you can find in your ``replica.my.cnf`` file on `Tool Labs`_.)

.. _Tool Labs: https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database

Table mappings
==============

Tool Labs has different versions of tables that utilize indexing to improve performance. We'll want to take advantage of that.

* Go to the config directory with ``cd app/config``
* Create the file table_map.yml from the template: ``cp table_map.yml.dist table_map.yml``
* Set the contents of the file to the following::

    parameters:
      app.table.archive: 'archive_userindex'
      app.table.revision: 'revision_userindex'
      app.table.logging: 'logging_logindex'

Sometimes we want <code>logging_userindex</code> and not the logindex. This is handled in the code via the <code>getTableName()</code> function in [https://github.com/x-tools/xtools-rebirth/blob/master/src/Xtools/Repository.php#L144 Repository.php].

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

Writing the docs
================

We use ReadTheDocs; it's great.

To build this documentation locally, you need ``python-sphinx`` installed,
as well as the ``sphinx_rtd_theme`` theme_.

.. _theme: https://github.com/rtfd/sphinx_rtd_theme

Then, it's simply a matter of runnign ``make html`` in the ``docs/`` directory,
and browsing to ``xtools/docs/_build/html/`` to view the documentation.

Documentation sections use the following (standard Python) hierarchy of section symbols:

* ``#`` with overline for parts
* ``*`` with overline for chapters
* ``=`` for sections
* ``-`` for subsections

Additional Help
===============

Please contact `User:Matthewrbowker <https://en.wikipedia.org/wiki/User:Matthewrbowker>`_ or `User:MusikAnimal <https://en.wikipedia.org/wiki/User:MusikAnimal>`_ if you need help.
Or, you are welcome to visit us on `IRC <https://webchat.freenode.net/?channels=#wikimedia-xtools>`_ (`Direct link <irc://irc.freenode.net/#wikimedia-xtools>`_ - Requires an IRC client).
