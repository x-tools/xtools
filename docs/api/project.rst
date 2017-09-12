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
