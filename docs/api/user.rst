.. _user:

########
User API
########

API endpoints related to a user.

Non-automated edits
===================
``GET /api/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}``

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

    https://xtools.wmflabs.org/api/nonautomated_edits/en.wikipedia/Jimbo_Wales/0
