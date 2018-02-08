.. _configuration:

#############
Configuration
#############

As part of the installation of XTools, ``composer install`` or ``composer update`` may prompt you for configuration options.

Databases
=========

XTools' own database:

- **database_host** - Hostname for the server with the XTools database.
- **database_port** - Port for the server with the XTools database.
- **database_name** - Database name of the XTools database.
- **database_user** - Username for the XTools database.
- **database_password** - Password for the user for the XTools database

The projects' databases:

- **database_replica_host** - Hostname for the server with the MediaWiki databases. If you are :ref:`developing against the WMF replicas <development_wmf_replicas>` through an SSH tunnel, the value should probably be ``127.0.0.1``.
- **database_replica_port** - Port for the server with the MediaWiki databases. If you are developing against the WMF replicas through an SSH tunnel, the value should the MySQL port that was forwarded.
- **database_replica_name** - Database name of any one of the MediaWiki databases (usually the default, or the 'meta'; it doesn't matter which). For installations connecting to the WMF replicas, this could for example be ``metawiki_p``.
- **database_replica_user** - Username for the MediaWiki databases. If you are developing against the WMF replicas, you should use the credentials specified in the ``replica.my.cnf`` file, located in the home directory of your Toolforge account.
- **database_replica_password** - Password for the user for the MediaWiki databases.

The 'meta' database:

- **database_meta_name** - Database name for the server with the ``wiki`` table (this is not required if ``app.single_wiki`` is set). If connecting to the WMF replicas, the value should be ``meta_p``.

For WMF installations, you should also specify credentials for the ``tools-db`` database server, which contains data from other tools (e.g. checkwiki_):

- **database_toolsdb_host** - MySQL host name (``127.0.0.1`` if connecting to the replicas via SSH tunnel).
- **database_toolsdb_port** - MySQL port number.
- **database_toolsdb_name** - Username to connect as (should be the same as ``database_replica_user``).
- **database_toolsdb_password** - Password to use for the user (should be the same as ``database_replica_password``).

.. _checkwiki: https://tools.wmflabs.org/checkwiki/

Authentication and Email
========================

OAuth is used to allow users to make unlimited requests, and to allow them to see :ref:`detailed statistics <optin>` of their own account. The credentials need to be requested from ``Special:OAuthConsumerRegistration`` on your default wiki. This requires the `OAuth extension <https://www.mediawiki.org/wiki/Extension:OAuth>`_. If this extension is not installed or you are developing locally, you can leave these options blank.

- **oauth_key** - Oauth consumer key.
- **oauth_secret** - Oauth consumer secret.
- **mailer_transport** - Software for the mailer. For development installations, you can leave all the mailer configuration options blank.
- **mailer_host** - Hostname for the mailer.
- **mailer_user** - Username for the mailer software.
- **mailer_password** - Password for the mailer software.

Caching
=======

These options are available if you wish to use a cache provider such as Redis. However, XTools will function well using only the file system for caching.

- **cache.adapter** A cache adapter supported by `DoctrineCacheBundle <https://symfony.com/doc/current/bundles/DoctrineCacheBundle/reference.html>`_. `file_system` is the default and should work well.
- **cache.redis_dsn** The DSN of the Redis server, if ``redis`` is used as the ``cache.adapter``. If you're not using Redis, this parameter can be ignored.

Wiki configuration
==================

The parameters ensures paths to your wiki(s) are properly constructed, and for communicating with the API.

- **wiki_url** - Full URL of the wiki, used only if ``app.single_wiki`` is set to ``true``. The title of pages are attached to the end.
- **api_path** - The API path for the projects, usually ``/w/api.php``.
- **default_project** - The base URL of whatever wiki you consider to be the "default". This will be the default value for the "Project" field in the forms. On the Wikimedia installation, ``en.wikipedia.org`` is used because it is the most popular wiki. For single-wiki installations, the "Project" field in the forms are hidden, but you still need to provide this value for ``default_project``.
- **opted_in** - A list of database names of projects that will display :ref:`restricted statistics <optin>` regardless of individual users' preferences. For developers working off of the replicas, use ``enwiki_p``.

Application
===========

- **secret** - A secret key that's used to generate certain security-related tokens, and as the secret for the internal API. This can be any non-blank value. If you are using a separate API server (as explained in the :ref:`administration <offload_api>` section), this parameter must have the same value on both the app server and API server.
- **app.noticeDisplay** - This is used to broadcast a notice at the top of XTools. Set to ``1`` to turn this feature on.
- **app.noticeStyle** - Style of the notice banner, correlating to the `Bootstrap contextual classes <https://getbootstrap.com/docs/3.3/css/#tables-contextual-classes>`_. Available options include ``danger``, ``warning``, ``info`` and ``success``.
- **app.noticeText** - Message shown to the user. If you provide a valid i18n message key, it will display that message instead.
- **app.load_stylesheets_from_cdn** - Whether to load our stylesheets and scripts from a CDN. This is required if XTools is installed on a Windows server.
- **app.single_wiki** - Point XTools to a single wiki, instead of using a meta database. This ignores ``database_meta_name`` above.
- **app.is_labs** - Whether XTools lives on the Wikimedia Foundation Cloud Services environment. If you are developing against the WMF replicas through an SSH tunnel, set this to ``true``.
- **app.replag_threshold** - Number of seconds to consider the replicas as "lagged", and show a warning to the user that the data may be out of date. For WMF installations, this parameter is obsolete and can be left blank, as the new replicas do not suffer from noticeable lag.
- **app.query_timeout** Maximum allowed time for queries to run. This is to ensure database quota is not exceeded.
- **app.rate_limit_time** - Used for :ref:`rate limiting <rate_limiting>`. This parameter is the number of minutes during which ``app.rate_limit_count`` requests from the same user are allowed. Set this to ``0`` to disable rate limiting.
- **app.rate_limit_count** - Number of requests from the same user that are allowed during the time frame specified by ``app.rate_limit_time``. Set this to ``0`` to disable rate limiting.
- **app.multithread** Set to 1 to speed up the Edit Counter and other tools by making multiple asynchronous queries. This requires a multithreaded server (such as Apache), so you should set this to ``0`` if you are using the default Symfony server in your development environment. It may also be possible to forward all requests to ``/api`` to a dedicated API server. See the :ref:`administration <offload_api>` section for more. You must also set the ``app.base_path`` parameter for multithreading to work.
- **app.base_path** The base URL of your XTools installation, including the protocol. This parameter is required if ``app.multithread`` is turned on.
- **app.max_page_revisions** - Set a maximum number of revisions to process for pages. This is to safeguard against unnecessarily consuming too many resources for queries that will most surely timeout. Set this to `0` to disable all limitations.
- **app.max_user_edits** - Querying a user that has more edits than this will be rejected. This is to safeguard against unnecessarily consuming too many resources for queries that will most surely timeout. Set this to `0` to disable all limitations.
- **languageless_wikis** - This should be left blank for any non-WMF installation. This is used only to convert legacy XTools URL parameters to the modern equivalents, listing any wikis where there is no specific language associated with it. "meta.wikimedia.org" is intentionally not included. Developers may also leave this value blank.

Tools
=====

Selectively choose which tools to enable within XTools.

- **enable.adminscore** - Enable "Admin Score" tool.
- **enable.adminstats** - Enable "Admin Statistics" tool.
- **enable.articleinfo** - Enable "Article Information" tool.
- **enable.autoedits** - Enable "Automated Edits" tool.
- **enable.bash** - Enable "Quote Database" tool.
- **enable.ec** - Enable "Edit Counter" tool.
- **enable.es** - Enable "Edit Summaries" tool.
- **enable.pages** - Enable "Pages Created" tool.
- **enable.rfx** - Enable "RfX Analysis" tool.
- **enable.rfxvote** - Enable "RfX Vote Calculator" tool.
- **enable.sc** - Enable "Plain, Dirty, Simple Edit Counter" tool.
- **enable.topedits** - Enable "Top Edits" tool.
