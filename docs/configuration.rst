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
- **database_meta_name** - Database Name for the server with the meta_p table

- **mailer_transport** - Software for the mailer
- **mailer_host** - Hostname for the mailer
- **mailer_user** - Username for the mailer software
- **mailer_password** - Password for the mailer software

- **secret** - A secret key that's used to generate certain security-related tokens

- **app.noticeDisplay** - Display the notice or not
- **app.noticeStyle** - Style of the notice banner.  Available options: "error," "warning," "succeess," "info."
- **app.noticeText** - Message shown to the user.  If you provide a valid intuition key, it will display that message instead

- **enable.ec** - Enable "Edit Counter" tool
- **enable.articleinfo** - Enable "Article Information" tool
- **enable.pages** - Enable "Pages Created" tool
- **enable.topedits** - Enable "Top Edits" tool
- **enable.rangecontribs** - Enable "Range Contributions" tool
- **enable.blame** - Enable "Article Blamer" tool
- **enable.autoedits** - Enable "Automated Edits" tool
- **enable.autoblock** - Enable "Autoblock" tool
- **enable.adminstats** - Enable "Admin Statistics" tool
- **enable.adminscore** - Enable "Admin Score" tool
- **enable.rfa** - Enable "RfX Analysis" tool
- **enable.rfap** - Enable "RfX Vote Calculator" tool
- **enable.bash** - Enable "Quote Database" tool
- **enable.sc** - Enable "Plain, Dirty, Simple Edit Counter" tool
