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

Admins and user groups
======================
``GET /api/project/admins_groups/{project}``

Get a list of users who are admins, bureaucrats, CheckUsers, Oversighters, or
stewards of the project and list which of these user groups they belong to.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.

**Example:**

Get administrative users of the French Wikipedia:

    https://xtools.wmflabs.org/api/project/admins_groups/frwiki
    https://xtools.wmflabs.org/api/project/admins_groups/fr.wikipedia.org

Admin statistics
================

``GET /api/project/adminstats/{project}/{days}``

Get users of the project that are capable of making 'admin actions', along with
various stats about the actions they took. Time period is limited to one month.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``days`` - Number of days before present to fetch data for (default 30, maximum 30).

**Example:**

Get various statistics about actions taken by admins of the French Wikipedia
over the past week:

    https://xtools.wmflabs.org/api/project/adminstats/frwiki/7
    https://xtools.wmflabs.org/api/project/adminstats/fr.wikipedia.org/7
