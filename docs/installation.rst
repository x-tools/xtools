************
Installation
************

To install xTools, please follow these steps:

1. Download the repository into your webserver root.
2. Run ``composer install``.
3. Ensure that ``var/`` and all files within it (other than ``var/SymfonyRequirements.php``) are writable by the web server.
4. Visit your domain in a browser.  You should see the xTools landing page.
5. In order to update the cache after making configuration changes, run ``./bin/console cache:clear``.
