.. _articleinfo:

************
Page History
************

The Page History tool, also known as "ArticleInfo", provides detailed
statistics about the revision history of a page.

General Statistics
==================

The general statistics section contains an overview of the statistics of the page.
This includes basic figures like the page size, number of editors, types of editors,
number of edits, and various averages.

On WMF wikis, the "Wikidata ID" field also shows the number of `sitelinks`. This figure refers
to the number of sister projects that have a page about the same subject.

For supported projects on WMF wikis, you may see additional information such as the
:ref:`assessment <assessments>` of the page, pageviews_ and the number of :ref:`bugs <bugs>`.

Beneath the numerical statistics are three charts. The first shows the number of edits
made by registered accounts compared to logged out users (IPs). The second chart shows
the number of edits that were marked as minor compared to major edits (not marked as minor).
The last chart shows the number of edits made by the top 10% of all editors to that page,
compared to the bottom 90%. The :ref:`top editors <topeditors>` are ranked by the amount
of content they've added to the page.

.. _pageviews: https://meta.wikimedia.org/wiki/Research:Page_view

.. _topeditors:

Top editors
===========

The top editors section shows various information about users and bots who have edited the page.
There are two pie charts comparing the top editors by :ref:`number of edits <number_of_edits>`
and by :ref:`added text <added_text>`. XTools does not count bot accounts as a top editor.
Instead, they are listed in the :ref:`bot list <bot_list>` table.

.. _number_of_edits:

By number of edits
------------------

The `Top 10 by edits` chart compares the number of edits each top editor made. The percentages
shown in parentheses refer to the number of edits the user made in relation to total number
of edits made to the page.

.. _added_text:

By added text
-------------

`Added text` refers to any positive addition of content that was not reverted with the next edit.
This is because users who fight vandalism (for instance) will otherwise appear to have added a lot
of content to a page, when in actuality they just undid an edit that removed a lot of content. Going
by edits that weren't reverted, we have a better idea of the users who made meaningful contributions.

Note however that the Page history tool only detects reverts if it happened with the very next edit,
and not a later edit.

The "Top 10 by added text" pie chart compares each of the 10 top editors. The percentages shown
in parentheses refer to the amount of content that user added compared to all content
that was added to the page.

Top editors table
-----------------

The first table shown lists the top editors (non-bots) and various statistics about their contributions
to the page. The last two columns show specialized calculations. `Average time between edits` (atbe) is
the average number of days between each of the user's edits to the page. This is starting with the
date of their first edit and the date of their last edit to the page. :ref:`Added (bytes) <added_text>`
refers to the number of bytes of text the user added to the page.

By default only the first 20 editors are shown. You can expand to show all editors using the link on the
bottom row of the table.

You can also export this data as wikitext using the link just above the table.

.. _bot_list:

Bot list
--------

The "Bot list" shows lists all of the bots that edited the page, ranked by edit count. A message is shown
indicating if the bot is no longer a bot, and links to the account's user rights log.

The list is by default limited to the top 10 bots. You can expand to show all bots using the link on the
bottom row of the table.

Year counts
===========

This section breaks down editing activity by each year.

The chart compares the number of edits, IP edits and minor edits over time. The yellow line represents
the total size of the article as it changed over time (the right Y-axis denotes the values).

The table lists various statistics for each individual year. `Log events` shows which logged events occurred
during that year. The types of events XTools looks for include deletions (e.g. page was deleted then restored),
page moves, protections that were applied, and stable settings (also known as pending changes protection).

Month counts
============

This section breaks down editing activity by each month. There is a small graph shown for each month, which
compares the number of total edits made to IP edits and minor edits.

(Semi-)automated edits
======================

This lists all the known (semi-)automated tools that were used to edit the page. For more information on how
this works, see the documentation on the :ref:`AutoEdits tool <autoedits>`.

.. _assessments:

Assessments
===========

Some WMF wikis have a system of rating the quality of a page, known as an "assessment". This section lists
any known assessments of the page from each WikiProject, based on PageAssessments_ data.

.. _PageAssessments: https://www.mediawiki.org/wiki/Extension:PageAssessments

.. _bugs:

Bugs
====

This section lists any issues with the page that were automatically detected. This includes missing basic
Wikidata, such as the description, and _CheckWiki errors. For both, a table is shown explaining each issue and
how to fix it. The "priority" indicates how important it is to fix the given issue according to CheckWiki, where
1 is the highest priority. "Notice" indicates where in the wikitext the issue lies.

.. _CheckWiki: https://tools.wmflabs.org/checkwiki/
