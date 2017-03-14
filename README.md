X's Tools
==============

This is a rewrite of the popular toolset originally written by [[User:X!]] on the English Wikipedia.

Refactored 2014 by Hedonil

Refactored 2016 by Matthewrbowker

[![Docs](https://readthedocs.org/projects/xtools/badge/?version=latest)](https://xtools.readthedocs.io/en/latest/?badge=latest)
[![Dependency Status](https://www.versioneye.com/user/projects/58c7654c2e726a000f720682/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/58c7654c2e726a000f720682)
[![Build Status](https://travis-ci.org/x-tools/xtools-rebirth.svg?branch=master)](https://travis-ci.org/x-tools/xtools-rebirth)

Installation
------------

To install xtools, follow these steps:

1. Clone or download this repository.
2. Run "composer install".  This will prompt for a series of questions, including database credentials.
3. Run in web server.
4. ???
5. Profit!

Currently, xtools requires two databases.  One for itself (normally called "symfony") and a meta database describing all of the wikis xTools should be aware of.  For the structure, see [Wikitech](https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database#Metadata_database).  an example, see [this Quarry](https://quarry.wmflabs.org/query/4031).

For full documentation, please see [ReadTheDocs](https://xtools.readthedocs.io).
