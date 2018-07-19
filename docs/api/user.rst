.. _user:

########
User API
########

API endpoints related to a user.

Simple edit count
=================
``GET /api/user/simple_editcount/{project}/{username}``

For the given account, get the user ID, live and deleted edit count,
local user groups and global user groups.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces.
* ``start`` - Start date in the format ``YYYY-MM-DD``.
* ``end`` - End date in the format ``YYYY-MM-DD``.

**Example:**

Get basic statistics about `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/simple_editcount/en.wikipedia/Jimbo_Wales

Get basic statistics about `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_, but only during
the year of 2014 and within the mainspace.

    https://xtools.wmflabs.org/api/user/simple_editcount/en.wikipedia/Jimbo_Wales/0/2014-01-01/2014-12-31

Number of pages created
=======================
``GET /api/user/pages_count/{project}/{username}/{namespace}/{redirects}/{deleted}``

Get the number of pages created by the user in the given namespace.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces.
* ``redirects`` - One of 'noredirects' (default), 'onlyredirects' or 'all' for both.
* ``deleted`` - One of 'live', 'deleted' or 'all' (default).

**Example:**

Get the number of mainspace, non-redirect pages created by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/pages_count/en.wikipedia/Jimbo_Wales

Get the number of article talk pages created by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ that are redirects.

    https://xtools.wmflabs.org/api/user/pages_count/en.wikipedia/Jimbo_Wales/1/onlyredirects

Pages created
=============
``GET /api/user/pages/{project}/{username}/{namespace}/{redirects}/{deleted}/{offset}``

Get the pages created by the user in the given namespace.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces.
* ``redirects`` - One of 'noredirects' (default), 'onlyredirects' or 'all' for both.
* ``deleted`` - One of 'live', 'deleted' or 'all' (default).
* ``offset`` - Which page of results to show. If there is more than one page of results, ``continue`` is returned, with the subsequent page number as the value.

**Example:**

Get the mainspace, non-redirect pages created by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/pages/en.wikipedia/Jimbo_Wales

Get the article talk pages created by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ that are redirects.

    https://xtools.wmflabs.org/api/user/pages/en.wikipedia/Jimbo_Wales/1/onlyredirects

Automated edit counter
======================
``GET /api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{offset}/{tools}``

Get the number of (semi-)automated edits made by the given user in the given namespace and date range.
You can optionally pass in ``?tools=1`` to get individual counts of each (semi-)automated tool that was used.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces.
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
* ``namespace`` (**required**) - Namespace ID or  ``all`` for all namespaces. Defaults to ``0`` (mainspace).
* ``start`` - Start date in the format ``YYYY-MM-DD``. Leave this and ``end`` blank to retrieve the most recent contributions.
* ``end`` - End date in the format ``YYYY-MM-DD``. Leave this and ``start`` blank to retrieve the most recent contributions.
* ``offset`` - Number of edits from the start date.

**Example:**

Get the latest non-automated mainspace contributions made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/nonautomated_edits/en.wikipedia/Jimbo_Wales
    https://xtools.wmflabs.org/api/user/nonautomated_edits/en.wikipedia/Jimbo_Wales/0

Automated edits
===============
``GET /api/user/automated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}``

Get (semi-)automated contributions for the given user, namespace and date range.
You can get edits only made with a specific tool by appending ``?tool=Tool name`` to the end of the URL.
To get the names of the available tools, use the :ref:`Automated tools <autotools>` endpoint.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` (**required**) - Namespace ID or  ``all`` for all namespaces. Defaults to ``0`` (mainspace).
* ``start`` - Start date in the format ``YYYY-MM-DD``. Leave this and ``end`` blank to retrieve the most recent contributions.
* ``end`` - End date in the format ``YYYY-MM-DD``. Leave this and ``start`` blank to retrieve the most recent contributions.
* ``offset`` - Number of edits from the start date.

**Example:**

Get the latest automated mainspace contributions made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/automated_edits/en.wikipedia/Jimbo_Wales
    https://xtools.wmflabs.org/api/user/automated_edits/en.wikipedia/Jimbo_Wales/0

Get Twinkle contributions made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ in the User talk
namespace, leading up to the year 2011.

    https://xtools.wmflabs.org/api/user/automated_edits/en.wikipedia/Jimbo_Wales/0//2011-01-01?tool=Twinkle

.. _autotools:

Automated tools
===============
``GET /api/user/automated_tools/{project}``

Get a list of the known (semi-)automated tools used on the given project.

**Response format:**

For each tool, the some or all of the following data is provided:

* ``tag``: A `tag <https://www.mediawiki.org/wiki/Help:Tags>`_ that identifies edits made using the tool.
* ``regex``: Regular expression that can be used against edit summaries to test if the tool was used.
* ``link``: Path to the tool's documentation.
* ``label``: Translation of the tool's name, if applicable and available.
* ``revert``: Whether or not the tool is exclusively used for reverting edits.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get all the known semi-automated tools used on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/automated_tools/en.wikipedia.org

Edit summaries
==============
``GET /api/user/edit_summaries/{project}/{username}/{namespace}``

Get statistics about a user's usage of edit summaries.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces.

**Example:**

Get `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_'s edit summary statistics on the English Wikipedia.

    https://xtools.wmflabs.org/api/user/edit_summaries/en.wikipedia/Jimbo_Wales

Top edits
=========
``GET /api/user/top_edits/{project}/{username}/{namespace}/{article}``

Get the top-edited pages by a user, or get all edits made by a user to a specific page.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``namespace`` - Namespace ID or ``all`` for all namespaces. Defaults to the mainspace. Leave this blank if you are also supplying a full page title as the ``article``.
* ``article`` - Full page title if ``namespace`` is omitted. If ``namespace`` is blank, do not include the namespace in the page title.

**Example:**

Get the top edits made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ in the mainspace.

    https://xtools.wmflabs.org/api/user/top_edits/en.wikipedia/Jimbo_Wales

Get the top edits made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ in the userspace.

    https://xtools.wmflabs.org/api/user/top_edits/en.wikipedia/Jimbo_Wales/2

Get the top edits made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ to the page `Talk:Naveen Jain <https://en.wikipedia.org/wiki/Talk:Naveen_Jain>`_.

    https://xtools.wmflabs.org/api/user/top_edits/en.wikipedia/Jimbo_Wales//Talk:Naveen_Jain
    https://xtools.wmflabs.org/api/user/top_edits/en.wikipedia.org/Jimbo_Wales/1/Naveen_Jain

Category edit counter
=====================
``GET /api/user/category_editcount/{project}/{username}/{categories}/{start}/{end}``

Get the number of edits made by the given user to the given categories.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``username`` (**required**) - Account's username.
* ``categories`` (**required**) - Category names separated by pipes. The namespace prefix may be omitted.
* ``start`` - Start date in the format ``YYYY-MM-DD``. Leave this and ``end`` blank to retrieve the most recent data.
* ``end`` - End date in the format ``YYYY-MM-DD``. Leave this and ``start`` blank to retrieve the most recent data.

**Example:**

Get the number of edits made by `Jimbo Wales <https://en.wikipedia.org/wiki/User:Jimbo_Wales>`_ to `Category:Living people <https://en.wikipedia.org/wiki/Category:Living_people>`_ and `Category:Wikipedia village pump <https://en.wikipedia.org/wiki/Category:Wikipedia_village_pump>`_.

    `<https://xtools.wmflabs.org/api/user/category_editcount/en.wikipedia/Jimbo_Wales/Living_people|Wikipedia_village_pump>`_
