###########
Development
###########

To contribute to the development of XTools, you may fork us on GitHub.  A few things to be aware of first:

1. XTools is based on Symfony 3. We use Twig as our template engine.  Symfony is a full MVC system.
   a. The controllers are located at ``src/AppBundle/controller``.  They are sorted by tool
   b. The twig templates are located at ``app/resources/views``.  They are sorted by tool.
2. We use the ``@Route`` syntax to configure routes. 
3. Every tool requires a twig directory and one controller. Also, core parts of XTools require the tool to be registered within `app/config/tools.yml`.

Style Guidelines
----------------

- It's called "XTools" (with two capital letters).
- We use 4 spaces to indent code.
- Opening and closing curly braces must be on their own lines.
- Variable names are camelCase.  Constants are ALL_CAPS_AND_UNDERSCORES.  Function names are camelCase.
- Functions and routes must begin with the tool name.

Running Development server
--------------------------

Follow these steps

1. Download the repository.
2. Run ``composer install``
3. Issue ``php bin/console server:run``.
4. Visit ``http://localhost:8000`` in your web browser.

The development server does not cache data.  Any changes you make are visible after refreshing the page.

Developing against WMF databases
--------------------------------

If you want to use the WMF database replicas, open a tunnel with::

    ssh -L 4711:enwiki.labsdb:3306 tools-login.wmflabs.org -N -l your-username-here

And set the following in ``app/config/parameters.yml``::

    app.is_labs: 1
    database_replica_host: 127.0.0.1
    database_replica_port: 4711
    database_replica_name: meta_p
    database_meta_name: meta_p
    database_replica_user: your-uxxxx-username-here
    database_replica_password: your-password-here

(Change the ``your-*-here`` bits to your own values.)

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
