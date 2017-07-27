##############
Administration
##############

Once you have XTools up and running, depending on how much traffic you receive, you might want to implement measures to ensure stability.

Adding rate limiting
====================

Rate limiting can safeguard against spider crawls and bots that overload the application.

To configure, set the following variables in ``parameters.yml``:

* ``app.rate_limit_time: 10`` where ``10`` is the number of minutes ``app.rate_limit_count`` requests from the same user to the same URI are allowed.
* ``app.rate_limit_count: 5`` where ``5`` is the number of requests from the same user that are allowed during the time frame specified by ``app.rate_limit_time``.

Using the above example, if you try to load the same page more than 5 times within 10 minutes, the request will be denied and you will have to wait 10 minutes before you can make the same request. This only applies to result pages and the API, and not index pages. Additionally, no rate limitations are imposed if the user is authenticated.

Any requests that are denied are logged at ``var/logs/rate_limit.log``.

Killing slow queries
====================

Some queries on users with a high edit count may take a very long time to finish or even timout. You may wish to add a query killer to ensure stability.

If you are running on a Linux environment, consider using `pt-kill <https://www.percona.com/doc/percona-toolkit/LATEST/pt-kill.html>`_. A query killer daemon could be configured like so:
::

    pt-kill --user=xxxx --password=xxxx --host=xxxx \
           --busy-time=90 \
           --log /var/www/web/killed_slow_queries.txt \
           --match-info "^(select|SELECT|Select)" \
           --kill --print --daemonize --verbose

This will kill any SELECT query that takes over 90 seconds to finish, and log the query at ``/var/www/web/killed_slow_queries.txt``.

Note that pt-kill requires libdbi-perl and libdbd-mysql-perl.
