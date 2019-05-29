.. _api:

###
API
###

.. note::
    Please first review the `MediaWiki API <https://www.mediawiki.org/wiki/API:Main_page>`_ and
    `REST API <https://wikimedia.org/api/rest_v1/#/>`_ to see if they meet your needs. These will be considerably
    faster than XTools and allow for asynchronous requests.

Response format
===============

All endpoints will return the requested parameters (such as ``project``, ``username``, etc.), the requested data,
followed by the ``elapsed_time`` of how long the request took to process in seconds.

Check the examples in the documentation for the exact format of each endpoint. All data is returned as JSON, in
addition to other formats as noted.

**This API is not versioned**. Make note of :ref:`warnings <errors_and_warnings>` in the response that will announce
deprecations and future changes.

.. _errors_and_warnings:

Errors and warnings
===================

Error messages will be given ``error`` key. Flash messages may also be shown with the keys ``info``, ``warning`` or
``danger``. Keep an eye out for ``warning`` in particular, which will announce deprecations.

To ensure performance and stability, most endpoints related to users will return an error if the user has made an
exceptionally high number of edits.

Endpoints
=========

.. toctree::

    project
    user
    page
    quote
