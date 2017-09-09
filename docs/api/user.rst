.. _user:

########
User API
########

API endpoints related to a user.

Automated edit counter
======================
``GET /api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{offset}/{tools}``

Get the number of (semi-)automated edits made by the given user in the given namespace and date range.
You can optionally pass in ``?tools=1`` to get individual counts of each (semi-)automated tool that was used.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` (**required**) - Namespace ID or 'all' for all namespaces.
* ``start`` - Start date in the format ``YYYY-MM-DD``. Leave this and ``end`` blank to retrieve the most recent data.
* ``end`` - End date in the format ``YYYY-MM-DD``. Leave this and ``start`` blank to retrieve the most recent data.
* ``tools`` - Set to any non-blank value to include the tools that were used and thier counts.

**Example:**

Get the number of (semi-)automated edits made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/automated_editcount/en.wikipedia/Jimbo_Wales

Get a list of the known (semi-)automated tools used by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ in the mainspace on the English Wikipedia, and how many times they were used.

    https://xtools.wmflabs.org/api/user/automated_editcount/en.wikipedia/Jimbo_Wales/0///1

Non-automated edits
===================
``GET /api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}``

Get non-automated contributions for the given user, namespace and date range.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` (**required**) - Namespace ID or 'all' for all namespaces.
* ``start`` - Start date in the format ``YYYY-MM-DD``. Leave this and ``end`` blank to retrieve the most recent contributions.
* ``end`` - End date in the format ``YYYY-MM-DD``. Leave this and ``start`` blank to retrieve the most recent contributions.
* ``offset`` - Number of edits from the start date.

**Example:**

Get the newest non-automated mainspace contributions made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/nonautomated_edits/en.wikipedia/Jimbo_Wales/0
