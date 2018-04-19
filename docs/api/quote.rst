.. _quote:

#########
Quote API
#########

API endpoints related to a `bash quotes <https://meta.wikimedia.org/wiki/IRC/Quotes>`_.

Random quote
============

Get a random bash quote.

``GET /api/quote/random``

**Example:**

Get a random bash quote.

    https://xtools.wmflabs.org/api/quote/random

Single quote
============

Get a quote by ID.

``GET /api/quote/{id}``

**Example:**

Get the quote with the ID of 5.

    https://xtools.wmflabs.org/api/quote/5

All quotes
==========

Get all available quotes.

``GET /api/quote/all``

**Example:**

Get all available quotes.

    https://xtools.wmflabs.org/api/quote/all
