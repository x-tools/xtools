*************
Configuration
*************

As part of the installation of xTools, ``composer install`` or ``composer update`` may prompt you for configuration options.  This is a definition
of those options.

- **database_host** - Hostname for the server with the symfony database
- **database_port** - Port for the server with the symfony database
- **database_name** - Database Name for the server with the symfony database
- **database_user** - Username for the server with the symfony database
- **database_password** - Password for the server with the symfony database

- **database_replica_host** - Hostname for the server with the MediaWiki database
- **database_replica_port** - Port for the server with the MediaWiki database
- **database_replica_name** - Database Name for the server with the MediaWiki database
- **database_replica_user** - Username for the server with the MediaWiki database
- **database_replica_password** - Password for the server with the MediaWiki database
- **database_meta_name** - Database Name for the server with the meta_p table (This is optional if app.single_wiki is set)

- **mailer_transport** - Software for the mailer
- **mailer_host** - Hostname for the mailer
- **mailer_user** - Username for the mailer software
- **mailer_password** - Password for the mailer software

- **secret** - A secret key that's used to generate certain security-related tokens

- **app.noticeDisplay** - Display the notice or not
- **app.noticeStyle** - Style of the notice banner.  Available options: "error," "warning," "succeess," "info."
- **app.noticeText** - Message shown to the user.  If you provide a valid intuition key, it will display that message instead
- **app.replag_threshold** - Number of seconds to consider the replicas as "lagged", and show a warning to the user that the data may be out of date

- **enable.ec** - Enable "Edit Counter" tool
- **enable.articleinfo** - Enable "Article Information" tool
- **enable.pages** - Enable "Pages Created" tool
- **enable.topedits** - Enable "Top Edits" tool
- **enable.blame** - Enable "Article Blamer" tool
- **enable.autoedits** - Enable "Automated Edits" tool
- **enable.adminstats** - Enable "Admin Statistics" tool
- **enable.adminscore** - Enable "Admin Score" tool
- **enable.rfa** - Enable "RfX Analysis" tool
- **enable.rfap** - Enable "RfX Vote Calculator" tool
- **enable.bash** - Enable "Quote Database" tool
- **enable.sc** - Enable "Plain, Dirty, Simple Edit Counter" tool

- **app.load_stylesheets_from_cdn** - Whether to load our stylesheets and scripts from a CDN.  This is required if xTools is installed on a Windows server
- **app.single_wiki** - Point xTools to a single wiki, instead of using a meta database.  This ignores database_meta_name above.
- **app.is_labs** - Whether xTools lives on the Wikimedia Foundation Labs environment.  This should be set to false.
- **wiki_url** - URL to use if app.single_wiki is enabled.  The title of pages is attached to the end.
- **api_path** - The API path for the project, usually /w/api.php
