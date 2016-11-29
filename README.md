X's Tools
==============

This is a rewrite of the popular toolset originally written by [[User:X!]] on the English Wikipedia.

Refactored 2014 by Hedonil

Refactored 2016 by Matthewrbowker

Installation
------------

To install xtools, follow these steps:

1. Clone or download this repository.
2. Run "composer install".  This will prompt for a series of questions, including database credientials.  
3. Run in web server.
4. ???
5. Profit!

Currently, xtools requires two databases.  One for itself (normally called "symfony") and a meta database describing all of the wikis xTools should be aware of.  For the structure, see [Wikitech](https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database#Metadata_database).  an example, see [this Quarry](https://quarry.wmflabs.org/query/4031).

For full documentation, please see [ReadTheDocs](https://xtools.readthedocs.io).
