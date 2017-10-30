.. _editcounter:

************
Edit Counter
************

The edit counter tool provides detailed summary statistics
about a single user on a single project.

General Statistics
==================

The general statistics section contains lots of statistics about the user and their work on the project,
as well as some data about other projects that they're active on.

Firstly, some basic **user information**: ID, username, and group membership
(including globally, if CentralAuth_ is installed).

Then, **Edit counts** are displayed for:

* the last day, week, month, year, and all time (the latter also including addition counts of deleted edits);
* edits made with or without comments;
* edits that have been deleted;
* small (under 20 bytes) and large (over 1000 bytes) edits;
* minor/non-minor edits (as recorded by the user); and
* what semi-automating tools they used to edit.

Also, dates of activity on the project (earliest and latest) are displayed,
and what this duration is in days.

Averages (per day) are given for some of the above metrics.

Next, **Page counts** are shown:

* pages created (note that this shows *all* pages created,
  including those created as redirects during a page move;
  the :ref:`Pages Created <pages>` tool excludes these);
* pages imported, moved, deleted, and undeleted;
* total number of unique pages edited.

And lastly, **Log counts** are summarized:

* the number of times the user has thanked_ another user;
* pages reviewed, patrolled, protected, and unprotected;
* users blocked and unblocked;
* files uploaded (and also those uploaded to Commons, for the WMF Labs installation).

.. _CentralAuth: https://www.mediawiki.org/wiki/Extension:CentralAuth
.. _thanked: https://www.mediawiki.org/wiki/Extension:Thanks

Namespace totals
================

Total edit counts in each namespace (from all time):
a table ordered in decreasing number of edits;
and a pie chart showing the relative number of edits.

Timecard
========

A 'punchcard' chart showing what days of the week and hours of the day the user made most edits.
The times given are in UTC.

Year counts
===========

A bar chart showing total edit counts made in each year,
with each bar being divided into namespace sections
so that it's possible to get an idea of how a user's namespace activity has changed over the years.

Month counts
============

The same as the year counts, except the columns are months instead of years.

Latest global edits
===================

A list of the user's thirty most recent edits from all projects.

Automated edits
===============

A summary table of the number of edits the user has made
with any of the known semi-automated editing tools,
sorted in decreasing order.
