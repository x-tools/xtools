# Release Notes #

## 3.1.38 ##
- In ArticleInfo, handle an issue with the WikiWho API where the
  usernames are blank.

## 3.1.37 ##
- Fix setting of shorter query timeout for ArticleInfo API.
- Handle some exceptions that are frequently thrown in production.

## 3.1.36 ##
- Major refactor of how queries are ran, adding a max query time
  so that they automatically time out (default 10 minutes).
- Fix some issues with parameter handling in the Pages Created tool,
  and only show what columns are relevant based on chosen options.
- Various other refactoring, bug fixes and improved test coverage.
- Localization updates.

## 3.1.35 ##
- Add more wikitext export options to the Edit Counter, including
  an option to in the form to get the entire results as wikitext.
- Fix a bug in TopEdits where it errored out if the page has only
  one edit by the requested user.
- Only accept valid parameters in the Pages tool, and hide columns
  that are irrelevant based on options (e.g. redirects when showing
  only deleted pages).
- Update AutoEdits regex for Arabic Wikipedia.
- Use 429 response code when throwing rate limiting error.
- Localization updates.

## 3.1.34 ##
- Add wikitext and CSV download options to the Edit Counter.
- T186111: Fix redirect in AdminScore if user is not found.
- Make namespace optional in Edit Summary tool.
- Localization updates.

## 3.1.33 ##
- T185411: Restore raw URL encoding. Instead incoming links that use
  the path parameter should encode the values accordingly.

## 3.1.32 ##
- T185850: Temporarily allow + as spaces, again.

## 3.1.31 ##
- T185411: Fix decoding of URL parameters.
- T185744: Fix counting of bot edits in ArticleInfo.
- T185675: Add support for four enwiki tools to AutoEdits.
- Localization updates.

## 3.1.30 ##
- Fix issue in TopEdits where the most recent edit was counted twice.

## 3.1.29 ##
- T179996, T179762: Use rev_sha1 for better revert detection in
  ArticleInfo and TopEdits.
- T179995: Rework single-page variant of TopEdits, with more information,
  visualizations, and using rev_sha1 for revert detection.
- Add namespace and date range options to Simple Edit Counter.
- Fix JavaScript column sorting.
- Localization updates.

## 3.1.28 ##
- AutoEdits: Better support for ar.wikipedia.
- ArticleInfo: Fix floating nav after authorship stats load.
- Localization updates.

## 3.1.27 ##
- Add prose, category, template and file statistics to ArticleInfo.
- UI refresh of the general stats section of ArticleInfo.
- Bug fixes in ArticleInfo when date ranges are provided.
- Some code refactoring and improved test coverage.

## 3.1.26 ##
- Fix rendering of Authorship template in ArticleInfo.
- Don't show Authorship in ArticleInfo if dates have been provided.

## 3.1.25 ##
- T181694: Add date range options to ArticleInfo.
- T176912: Add authorship attribution statistics (aka textshares)
  to ArticleInfo, powered by Wikiwho https://api.wikiwho.net/.
- T184809: Improve rollback, undo and page move detection in the
  AutoEdits tool for ar.wikipedia.
- Better detecting of auto-expiring rights and old formats, for
  the "Rights changes" feature of the Edit Counter.
- T184600: Add 'minus-x' library to fix permissions of repo files.
- Composer task to run full test suite ('composer test').
- Localization updates.

## 3.1.24 ##
- Add section to Edit Counter that lists legible user rights changes.
- Add new MediaWiki tags for rollback and undo to AutoEdits.
- T183757: Improve AutoEdits for ar.wikipedia.
- Add Evad37's rater.js to AutoEdits.
- Fix link to TopEdits from within EditCounter.
- Make site notices more prominent.
- Localization updates.

## 3.1.23 ##
- T172003: Add option to filter deleted pages to Pages Created tool.

## 3.1.22 ##
- T182997: Set query limit on ArticleInfo API.
- Localization updates.

## 3.1.21 ##
- Usage tracking of API.

## 3.1.20 ##
- T177677: Paginate results in the Pages Created tool.
- Add API endpoint for Pages Created.
- Major refactoring and code cleanup.
- Localization updates.

## 3.1.19 ##
- T181954: Fix display of page watchers in ArticleInfo.
- T179763: Link to documentation in Admin Score.
- T179764: Show data along with score in Admin Score.
- T179508: Fix checking of account age in Admin Score.
- Use specialized escaping of page titles in articleinfo.js.
- Fix bug with AutomatedEditsHelper affecting single-wiki installations.
- Localization updates.

## 3.1.18 ##
- T180803: Fix sorting of date column in Pages Created tool.
- T179313: Revive checking of basic Wikidata errors in ArticleInfo.
- Localization updates.

## 3.1.17 ##
- T179762: Don't include reverted edits with top editors in ArticleInfo.
- Remove edit count restriction when querying for an article in TopEdits.
- Add API for TopEdits.

## 3.1.16 ##
- T179293: Remove references to wikidatawiki_p.wb_entity_per_page which was
  removed with T95685. Checking basic wikidata fields will be reimplemented
  at a later time.
- T179304: Fix user opt-in check for usernames with spaces.

## 3.1.15 ##
- T179258: Don't use reserved characters in cache keys.

## 3.1.14 ##
- New Edit Summaries API endpoint.
- T178622: Show percentages when hovering over namespaces in the year/month
  counts charts in the Edit Counter tool.
- T178618: Fix default sorting of AdminStats.
- T178259: Fix links to redirect pages in the Pages Created tool.
- Improved test coverage and code quality.

## 3.1.13 ##
- Major refactoring of controllers, standardizing parsing and decoding of
  URL parameters.
- T178203: Speed up Pages Created query, and improve detection of pages
  created that have since been deleted.
- Show "no contributions found" on result pages rather than redirect to index.
- T175763: Cache results of ArticleInfo API if the query took an usual
  amount of time to finish.
- Major refactor of AdminStats, and improvements to ensure only report users
  who were at some point in a qualifying user group (with admin-like actions).
- New AdminStats API endpoint.
- Improved test coverage.

## 3.1.12 ##
- T177883: Improve TopEdits and Edit Counter performance by collecting
  top-edited pages across all namespaces with a single query.
- T177898: Scale bubbles of time card chart with screen size.

## 3.1.11 ##
- Fix counting of Top Edits that broke when joining on page_assessments.
- T174581: Ensure bars of year/month counts in Edit Counter are of consistent
  size, and downsize them overall for better readability.
- Remove namespace toggles above year/month count charts in the Edit Counter,
  instead going off of toggles in the namespace counts table.

## 3.1.10 ##
- T177730: Show per-namespace breakdown of top edited pages.
- T177696: Fix ordering of Edit Counter timecard data.

## 3.1.9 ##
- T172801: Show top edited pages in Edit Counter.
- Downsize the timecard based on feedback.

## 3.1.8 ##
- Revert back to Chart.js v2.6.0

## 3.1.7 ##
- Hotfix for async queries of internal API. This requires a new parameter
  'app.base_path' be defined.

## 3.1.6 ##
- Hotfix to move internal 'usage API' out of /api namespace and into /meta.
  This is because the Wikimedia installation reroutes /api requests to a
  different server, which we don't want for the usage API.

## 3.1.5 ##
- T170652 Add option to limit how many edits to analyize in the Edit Counter,
  Top Edits, and Automated Edits tools, and a revision limit option for
  ArticleInfo.
- T176030 Localize all numbers across the application based on language.
- T177300 Fix links to Top Edits from the Edit Counter.
- T177089 Make Edit Counter internal API only accessible by XTools.
- Make autoedits API endpoint also return number of nonautomated edits.

## 3.1.4 ##
- T177172 Fix path to normalize project API endpoint
- T174012 Rework "longest block" field in Edit Counter to show actual duration
  of the block
- T177168 Fix sorting of 'atbe' column in ArticleInfo
- T177137 Add 'RotateLink' tool to AutoEdits
- T177138 Add 'Hotcatcheck' tool to AutoEdits
- T177140 Fix link to Global replace tool in AutoEdits
- Include current URL in bug report link
- Update all controllers to support routes of legacy XTools

## 3.1.3 ##
- Hotfix for showing mainspace page titles in non-automated edits API endpoint

## 3.1.2 ##
- T163284 Add option to optimize Edit Counter by querying internal API
  asynchronously
- T176676 Add missing routes with a trailing slash
- T176590, T176591 Add numerous Commons tools to AutoEdits, along with some bug
  fixes and performance improvments
- T175796 Fix display of replication lag
- Revamp API endpoint routing to be object-oriented

## 3.1.1 ##
- T174527 Fix caching of year/month counts in Edit Counter
- T172162 Fix sorting of some columns in AdminStats, make heading row sticky
- T170101 Endpoint to fetch JS for ArticleInfo gadget
- T170763 Resolve www. domains (accept www.wikidata.org or wikidata.org)
- Code refactoring and unifying the headers and user links atop each tool
- Fix 'average time between edits' statistic in ArticleInfo
- Various i18n fixes and updates from translatewiki

## 3.1.0 ##
- T165709, T165710 Introduce "RfX Analysis" and "RfX Voter" tools, both of which
  are functional but still a work in progress.
- T172915 Fix the time since last edit in ArticleInfo API, and add date of
  page creation
- T172883 Improve display of bubbles within EditCounter timecard so that they
  don't overlap the Y-axis labels
- T173173 Fix edit summary charts in EditCounter
- T172907 Minify and version assets in production
- T173483 Fix "links to this page" and "redirects" in ArticleInfo
- T173795 Fix i18n bug in AdminStats
- T173497 Limit the size of page display titles to avoid disruption the layout
- T173690 Add XfDCloser to list of enwiki's semi-automated tools
- Add IABot to list of enwiki's semi-automated tools
- Fix toggle chart in AutoEdits and show % of all tools and total edit count
- Fix bug in fetching pageviews from pages that are subpage of another page
- i18n updates from translatewiki

## 3.0.6 ##
- T171277 Add totals for year/month counts in EditCounter, make charts responsive
- Fixes to ArticleInfo API, making on-wiki XTools gadget possible
- T168896 Add throttling to prevent spider crawls and bots overloading the app
- T171814 Refinements to AdminScore, showing data for AIV, RFPP and AfD
- T172880 Make time duration language more human-readable
- T172792 Fix checking of local EditCounterOptIn.js for EditCounter stats
- T172799 Fix 'large edits' pie chart in EditCounter
- T172045 Fix inverted colours of summary pie chart in EditCounter
- T171815 Show currently selected language in language dropdown
- T171126 Fix redirect loop in AdminScore
- T169955 Generalize pages created count
- Improve performance of checking block log by specifying namespace
- Various improvements to i18n messages
- Localization updates from translatewiki.net

## 3.0.5 ##
- T170905 New "Edit Summaries" tool to analyize edit summary usage. Defaulted off.
- T171135 Fix ArticleInfo to properly reference project when detecting autoedits
- T170961 Fix link to Page Created from the Edit Counter
- T170608 Fix divison by zero warnings in Edit Counter
- T171133 URL-encode page titles and usernames when linking to a wiki
- T170233 Only use the AppBundle for Assetic in production
- Various styling fixes, mobile compatibility

## 3.0.4 ##
- T170050 Better cross-wiki support of AutoEdits tool
    - Include link to request a new semi-automated tool be added
- T170888 Fix namespace selection in AutoEdits
- T170988 Fix pie chart in AutoEdits tool
- T170894 Add messages indicating all times are in UTC
- T170809 Fix URLs to pages in formatted edit summaries
- Treat pages with invalid titles as nonexistent

## 3.0.3 ##
- T170808 Bug fix to allow pages with apostophes in the title

## 3.0.2 ##
- T170185 Remove automated edits interface in Edit Counter

## 3.0.1 ##
- T170367 Figure out XTools Git Repositories
- PR46 Wikimedia account instead of Phabricator

## 3.0.0 ##
- Converted XTools core to Symfony
- Converted the following tools to Symfony
    - Edit Counter
    - Article Information
    - Paged Created
    - Top Edits
    - Automated Edit Counter
    - Administrator Stats
    - Quote Database
    - Simple Edit Counter
- Removed the following tools
    - Article Blamer
    - Range Contributions
    - Autoblock Calculator
- Added ability for XTools to run outside of the WMF Tool Forge environment
- Allow XTools to run against a single wiki
- Allow XTools to utilise Bootstrap CDN
- Added ability to turn on and off tools
- Added custom error pages
- Added replication lag check to every page load
- Added ability to show global groups in Edit Counter and Simple Edit Counter
- Added unit tests
