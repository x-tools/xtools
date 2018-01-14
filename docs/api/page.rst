.. _page:

########
Page API
########

API endpoints related to a single page.

Article info
============
``GET /api/page/articleinfo/{project}/{article}/{format}``

Get basic information about the history of a page.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``article`` (**required**) - Full page title.

**Example:**

Get basic information about `Albert Einstein <https://en.wikipedia.org/wiki/Albert_Einstein>`_.

    https://xtools.wmflabs.org/api/page/articleinfo/en.wikipedia.org/Albert_Einstein

Prose
=====
``GET /api/page/prose/{project}/{article}``

Get statistics about the prose (characters, word count, etc.) and referencing of a page.

**Parameters:**

* ``project`` (**required**) - Project domain or database name.
* ``article`` (**required**) - Full page title.

**Example:**

Get prose statistics of `Albert Einstein <https://en.wikipedia.org/wiki/Albert_Einstein>`_.

    https://xtools.wmflabs.org/api/page/prose/en.wikipedia.org/Albert_Einstein
