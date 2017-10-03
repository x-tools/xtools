.. _configuration:

#############
Configuration
#############

As part of the installation of XTools, ``composer install`` or ``composer update`` may prompt you for configuration options.  This is a definition
of those options.

Databases
=========

XTools' own database:

- **database_host** - Hostname for the server with the XTools database
- **database_port** - Port for the server with the XTools database
- **database_name** - Database name of the XTools database
- **database_user** - Username for the XTools database
- **database_password** - Password for the user for the XTools database

The projects' databases:

- **database_replica_host** - Hostname for the server with the MediaWiki databases
- **database_replica_port** - Port for the server with the MediaWiki databases
- **database_replica_name** - Database name of any one of the MediaWiki databases (usually the default, or the 'meta'; it doesn't matter which).
- **database_replica_user** - Username for the MediaWiki databases
- **database_replica_password** - Password for the user for the MediaWiki databases

The 'meta' database:

- **database_meta_name** - Database Name for the server with the meta_p table (this is not required if ``app.single_wiki`` is set)

Other tools' database (e.g. checkwiki_):

- **database_toolsdb_host** - MySQL host name
- **database_toolsdb_port** - MySQL port number
- **database_toolsdb_name** - Username to connect as
- **database_toolsdb_password** - Password to use for the user

.. _checkwiki: https://tools.wmflabs.org/checkwiki/

Authentication and Email
========================

The Oauth details need to be requested from ``Special:OAuthConsumerRegistration`` on your default wiki.

- **oauth_key** - Oauth consumer key
- **oauth_secret** - Oauth consumer secret
- **mailer_transport** - Software for the mailer
- **mailer_host** - Hostname for the mailer
- **mailer_user** - Username for the mailer software
- **mailer_password** - Password for the mailer software

Application
===========

- **secret** - A secret key that's used to generate certain security-related tokens
- **app.noticeDisplay** - Display the notice or not
- **app.noticeStyle** - Style of the notice banner.  Available options: "error," "warning," "succeess," "info."
- **app.noticeText** - Message shown to the user.  If you provide a valid intuition key, it will display that message instead
- **app.replag_threshold** - Number of seconds to consider the replicas as "lagged", and show a warning to the user that the data may be out of date
- **app.load_stylesheets_from_cdn** - Whether to load our stylesheets and scripts from a CDN.  This is required if XTools is installed on a Windows server
- **app.single_wiki** - Point XTools to a single wiki, instead of using a meta database.  This ignores database_meta_name above.
- **app.is_labs** - Whether XTools lives on the Wikimedia Foundation Labs environment.  This should be set to false.
- **app.rate_limit_time** - Number of minutes during which ``app.rate_limit_count`` requests from the same user are allowed. Set this to ``0`` to disable rate limiting.
- **app.rate_limit_count** - Number of requests from the same user that are allowed during the time frame specified by ``app.rate_limit_time``. Set this to ``0`` to disable rate limiting.
- **app.multithread** Set to 1 to speed up the Edit Counter and other tools by making multiple asynchronous queries. This requires a multithreaded server (such as Apache), so you should set this to 0 if you are using the default Symfony server in your development environment. It may also be possible to forward all requests to ``/api`` to a dedicated API server. See the :ref:`administration <offload_api>` section for more.
- **wiki_url** - URL to use if app.single_wiki is enabled.  The title of pages is attached to the end.
- **api_path** - The API path for the project, usually /w/api.php
- **opted_in** - A list of database names of projects that will display :ref:`restricted statistics <optin>` regardless of individual users' preferences

Tools
=====

- **enable.ec** - Enable "Edit Counter" tool
- **enable.articleinfo** - Enable "Article Information" tool
- **enable.pages** - Enable "Pages Created" tool
- **enable.topedits** - Enable "Top Edits" tool
- **enable.blame** - Enable "Article Blamer" tool
- **enable.autoedits** - Enable "Automated Edits" tool
- **enable.adminstats** - Enable "Admin Statistics" tool
- **enable.adminscore** - Enable "Admin Score" tool
- **enable.rfa** - Enable "RfX Analysis" tool
- **enable.rfavote** - Enable "RfX Vote Calculator" tool
- **enable.bash** - Enable "Quote Database" tool
- **enable.sc** - Enable "Plain, Dirty, Simple Edit Counter" tool
- **enable.es** - Enable "Edit Summaries" tool
