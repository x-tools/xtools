# Release Notes #

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
