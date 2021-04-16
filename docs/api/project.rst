###########
Project API
###########

API endpoints related to a project.

Normalize project
=================
``GET /api/project/normalize/{project}``

Get the URL, database name, domain and API path of a given project.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Basic access information about the English Wikipedia.

    https://xtools.wmflabs.org/api/project/normalize/enwiki
    https://xtools.wmflabs.org/api/project/normalize/en.wikipedia
    https://xtools.wmflabs.org/api/project/normalize/en.wikipedia.org

Namespaces
==========
``GET /api/project/namespaces/{project}``

Get the localized names for each namespace of the given project.
The API endpoint for the project is also returned.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get the namespace IDs and names of the German Wikipedia.

    https://xtools.wmflabs.org/api/project/namespaces/dewiki
    https://xtools.wmflabs.org/api/project/namespaces/de.wikipedia
    https://xtools.wmflabs.org/api/project/namespaces/de.wikipedia.org

Page assessments
================
``GET /api/project/assessments/{project}``

Get page assessment metadata for the given project. This includes all the
different quality classifications and importance levels, along with their
associated colours and badges.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get page assessments metadata for the English Wikipedia.

    https://xtools.wmflabs.org/api/project/assessments/enwiki
    https://xtools.wmflabs.org/api/project/assessments/en.wikipedia
    https://xtools.wmflabs.org/api/project/assessments/en.wikipedia.org

Page assessments configuration
==============================
``GET /api/project/assessments``

Get a list of wikis that support page assessments, and the configuration
for each. This includes all the different quality classifications and
importance levels, along with their associated colours and badges.

**Example:**

Get the XTools Page Assessments configuration:

    https://xtools.wmflabs.org/api/project/assessments

Automated tools
===============
``GET /api/project/automated_tools/{project}``

Get a list of the known (semi-)automated tools used on the given project.

**Response format:**

For each tool, the some or all of the following data is provided:

* ``tag``: A `tag <https://www.mediawiki.org/wiki/Help:Tags>`_ that identifies edits made using the tool.
* ``regex``: Regular expression that can be used against edit summaries to test if the tool was used.
* ``link``: Path to the tool's documentation.
* ``label``: Translation of the tool's name, if applicable and available.
* ``revert``: Whether or not the tool is exclusively used for reverting edits.
* ``namespaces``: Which namespaces the tool is used in.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get all the known semi-automated tools used on the English Wikipedia.

    https://xtools.wmflabs.org/api/project/automated_tools/en.wikipedia.org

Admins and user groups
======================

.. seealso:: The MediaWiki `Allusers API <https://www.mediawiki.org/wiki/API:Allusers>`_.

``GET /api/project/admins_groups/{project}``

Get a list of users who are capable of making admin-like actions, and the relevant user groups they are in.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get administrative users of the French Wikipedia:

    https://xtools.wmflabs.org/api/project/admins_groups/frwiki
    https://xtools.wmflabs.org/api/project/admins_groups/fr.wikipedia.org

.. _admin_statistics:

Admin statistics
================

``GET /api/project/admin_stats/{project}/{start}/{end}``

Get users of the project that are capable of making 'admin actions', along with
counts of the actions they took. Time period is limited to one month.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``start`` - Start date in the format ``YYYY-MM-DD``. Defaults to 31 days before ``end``.
* ``end`` - End date in the format ``YYYY-MM-DD``. Defaults to current day (UTC).

The date range defaults to the past 31 days, and is limited to a 31-day period. If you need a wider range of data,
you must make the the individual requests (synchronously), and do the math in your application.

**Query string parameters:**

Optional `query string <https://en.wikipedia.org/wiki/Query_string>`_ parameters to
further filter results.

* ``actions`` - A pipe-separated list of 'actions' you want to query for. Defaults to all
  available actions. Query only for the actions you care about to get faster results.
  Available actions include:
    * ``delete``
    * ``revision-delete``
    * ``log-delete``
    * ``restore``
    * ``re-block``
    * ``unblock``
    * ``re-protect``
    * ``unprotect``
    * ``rights``
    * ``merge``
    * ``import``
    * ``abusefilter``

If you are interested in exactly which permissions are used in the queries, please review
the `YAML configuration <https://github.com/x-tools/xtools/blob/main/config/admin_stats.yml>`_.

**Example:**

Get 're-block' and 'abusefilter' statistics for every active admin on the French Wikipedia:

    `<https://xtools.wmflabs.org/api/project/admin_stats/fr.wikipedia?actions=re-block|abusefilter>`_

Get statistics about all relevant actions taken by Spanish Wikipedia admins in January 2019:

    https://xtools.wmflabs.org/api/project/admin_stats/es.wikipedia/2019-01-01/2019-01-31

Patroller statistics
====================

``GET /api/project/patroller_stats/{project}/{start}/{end}``

Same as :ref:`Admin statistics <admin_statistics>`, except with these ``actions``:

* ``patrol``
* ``page-curation``
* ``pc-accept``
* ``pc-reject``

**Example:**

Get 'patrol' and 'page-curation' statistics for relevant users on
the English Wikipedia over the 31 days:

    https://xtools.wmflabs.org/api/project/patroller_stats/en.wikipedia

Stewards statistics
===================

``GET /api/project/steward_stats/{project}/{start}/{end}``

Same as :ref:`Admin statistics <admin_statistics>`, except with these ``actions``:

* ``global-account-un-lock`` (global locks and unlocks)
* ``global-block``
* ``global-unblock``
* ``global-rename``
* ``global-rights``
* ``wiki-set-change``

**Example:**

Get statistics on stewards who have made global blocks and rights changes in January 2019:

    https://xtools.wmflabs.org/api/project/steward_stats/en.wikipedia/2019-01-01/2019-01-31
