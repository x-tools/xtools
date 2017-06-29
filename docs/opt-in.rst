.. _optin:

##################################
Opting in to restricted statistics
##################################

Some statistics are considered private by some users,
such as the times of the day or year that they edit most
or the pages they've made most contributions to.

Although the data for these statistics is made available via MediaWiki's API,
users must explicitly opt in to make the aggregate forms available in XTools.
Alternatively, a whole project can be opted
in via the ``opted_in``
:ref:`configuration variable <configuration>`.

The affected tools are as follows:

* :ref:`Edit Counter <editcounter>`:

  * Monthly counts bar chart
  * Timecard punch chart

* :ref:`Top Edits <topedits>`:

  * Top edits per namespace

How to opt in
=============

To opt in, a user must create ``User:<username>/EditCounterOptIn.js`` on each wiki they want to opt in for.
This page should be created with any content (it just has to have *some* content).

To opt in on all projects, they must create ``User:<username>/EditCounterGlobalOptIn.js`` on the default project
(or, in the case of the WMF Labs installation, on Meta Wiki).
Again, the actual content of this page is irrelevant.

How to opt out
==============

To opt out the relevant user page (single-wiki or global; see above) should be blanked or deleted.
