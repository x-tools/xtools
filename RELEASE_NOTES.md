# Release Notes #

## 3.18.0 ##
- XTools API has been rewritten to conform to the OpenAPI Specification.
  Changes are summarized here, but a more consumer-friendly summary can be
  found on the wiki at https://w.wiki/6sMx
- AdminStats: show warnings when requesting unsupported actions.
- AdminStats: `/project/admins_groups` and `/project/users_groups` endpoints
  have been removed. Use `/project/{group}_groups` instead.
- ArticleInfo: `/articleinfo` endpoint removed. Use `/page/articleinfo` instead.
- ArticleInfo: `/page/assessments/{project}/{pages}` endpoint now has results
  nested under the `pages` property.
- ArticleInfo: `/page/automated_edits` endpoint now returns results under the
  `automated_tools` key instead of `auto_edits`, for consistency with other
  similar endpoints.
- AutoEdits: `/user/automated_tools` endpoint removed. Use `/project/automated_tools`
  instead.
- AutoEdits: the `/project/automated_tools` endpoint now has results nested under
  the `tools` property.
- Pages: the `/user/pages_count` endpoint now always groups results by namespace,
  even if a specific namespace is provided.
- Pages: the `/user/pages` endpoint now has a completely new response format.
  See https://w.wiki/6sMx for details.
- TopEdits: the `/user/top_edits` endpoint now has a completely different response
  format. See https://w.wiki/6sMx for details.
- XtoolsController: requests denied because the requested user made too many edits
  now returns a 501 (Not Implemented) response code. In the future, some data will
  be provided.
- XtoolsController: Normalize format of all API responses.
- ExceptionListener: all fatal exceptions now follow the format specified by
  [RFC 7807](https://datatracker.ietf.org/doc/html/rfc7807).

## 3.17.12 ##
- ArticleInfo: Correct the name of the `ip_edits` property in the ArticleInfo
  API response.

## 3.17.11 ##
- ArticleInfo: added a "bot data" and "(semi-)automated edits" API endpoints.
  See https://www.mediawiki.org/wiki/XTools/API/Page#Bot_data for more info.
- ArticleInfo: the [article info API](https://www.mediawiki.org/wiki/XTools/API/Page#Article_info)
  now returns an `ip_edits` property.
- Localization updates.

## 3.17.10 ##
- Authorship: Add wikis that were recently added upstream in WikiWho
- PageAssessments: add zhwiki to config
- RateLimitSubscriber: deny access if crawler is detected
- ArticleInfo: show the number of revisions deleted, if applicable
- Improve handling of upstream errors
- Localization updates.

## 3.17.9 ##
- Edit: fix bug where 'rev_deleted' may be a string and not an integer.

## 3.17.8 ##
- Authorship: Enable for hu.wikipedia.org and it.wikipedia.org
- Indicate when information has been revision-deleted or suppressed.
- Translation updates.

## 3.17.7 ##
- Authorship: Enable for fr.wikipedia.org
- AutoEdits (T281962): make relevant links have usesandbox=1 when in sandbox mode
- Gracefully handle downtime of upstream services with a nicer error message.
- ArticleInfo: fail gracefully when prose stats cannot be fetched.
- ArticleInfo: fail gracefully if the pageviews API is down.
- Fix paths to favicon and error page images.

## 3.17.6 ##
- (T335313) Ensure all charts load after the DOM is ready.
- Fix the Meta tool.
- Other minor changes for PHP 8 compatibility.

## 3.17.5 ##
- EditCounter (T335211): Fix display of charts
- EditCounter: Fix issue with average edit size not being parsed as a float
- ArticleInfo, Edit: permit length property to be null
- GlobalContribs: skip nonexistent projects from global edit counts
- Page: make pageInfo property nullable
- Localization updates.

## 3.17.4 ##
- PageRepository: percent encode page title when fetching from REST API

## 3.17.3 ##
- Edit: make the SHA property nullable; fixes a prod error

## 3.17.1 ##
- Add unique index to usage_api_timeline

## 3.17.0 ##
- (T331939): Major refactor, upgrade to Symfony 5.4 and update all dependencies
- (T276885): Switch to db session management so that it persists to the API server
- ArticleInfo (T322062): Restore the count of templates used on the page
- ArticleInfo (T330548): Improve prose size algorithm
- ArticleInfo (T240267): Fix calculation for average time between edits
- EditCounter (T251837): Add option to show timecard in browser's timezone
- EditCounter (T323732): Fix message about top 10 editors
- EditCounter: Use OOUI icons for yes/no and add alt/title attributes
- AutoEdits (T286399): make autoedits-contributions page use localized tool names
- (T304191): Improve display of bash quote in footer on small screens
- AdminScore (T318027): Fix checking if user has a user page
- Add .editorconfig file to keep IDE behaviour consistent
- Localization updates.

## 3.16.1 ##
- ArticleInfo: ensure 'editorlimit' param is an integer
- GlobalContribs: skip projects that aren't replicated
- Localization updates.

## 3.16.0 ##
- GlobalContribs (T294765): add API endpoint
- UserRepository (T294765): do not report expired user rights
- Blame: gracefully handle missing revisions
- Localization updates.

## 3.15.3 ##
- Fix querying of replication lag.

## 3.15.2 ##
- ArticleInfo: revert forcing of UTF-8 on usernames

## 3.15.1 ##
- ArticleInfo: fix sorting for table columns with percentages
- ArticleInfo, User: force UTF-8 on usernames
- GlobalContribs (T309453): fix bug with project not being replicated
- Localization updates.

## 3.15.0 ##
- EditCounter (T284331): show number of content model changes
  under Admin Actions.
- EditCounter (T283542): show number of Thanks received by the user.
- EditCounter (T270998): show 3 decimal places for average edit figures.
- EditCounter (T299411): add support for global expiring user rights.
- EditCounter (T290534): link to Special:Log for upload counts.
- ArticleInfo (T303724): show warning when all usernames are missing.
- ArticleInfo (T253464): fix bot data when revs processed is less
  than total revs.
- ArticleInfo: escape backlashes in gadget.
- ArticleInfo: catch ServerException when Wikimedia API is down.
- TopEdits (T302470): Restore pagination widget.
- Fix dark mode styles for pagination widget.
- T285074: Escape usernames in external links.
- T301629: Remove default 'limit' value on contribs lists.
- Other minor styling tweaks and code cleanup.
- Localization updates.

## 3.14.4 ##
- (T284874): Refactor pagination and limit parameter in contribs lists.
- Authorship (T288840): switch to use new WikiWho installation.
- Localization updates.

## 3.14.3 ##
- Rework dark mode styles.

## 3.14.2 ##
- EditCounter (T297592): fix parsing of global user rights changes.
- Localization updates.

## 3.14.1 ##
- GlobalContribs: link to Special:CentralAuth or Special:Contribs in header.
- ArticleInfo: fix categories for importance levels for en.wikipedia
- Localization updates.

## 3.14.0 ##
- LargestPages: new tool to view the largest pages on a wiki.
- GlobalContribs: show warning when requesting IP and unknown namespace.
- TopEdits: change 'hist' links to link to page history starting with
  the edit in question.
- ArticleInfo: retire the uglified version of the gadget.
- Bump Node dependencies.
- EditCounter (T224654): rm extraneous spacing from lists of log actions.
- Add <bdi> around usernames for bi-directional support.
- ArticleInfo: hide IP edit count from API output since it's always 0.
- TopEdits: hide charts from mobile view.
- Localization updates.

## 3.13.4 ##
- TopEdits: show mw-reverted edits as reverted
- Allow focusing on input elements by passing i.e. ?focus=username in
  the URL, where 'username' is the value of the name attribute.
- AdminStats (T284298): Fix detection of interwiki imports.
- Localization updates.

## 3.13.3 ##
- AdminStats (T284070): Add content model changes
- GlobalContribs (T284250): Fix display of project domain.
- GlobalContribs: make the 'User' column sortable for IP ranges.
- Localization updates.

## 3.13.2 ##
- EditCounter: Fix display of block log info and admin actions.
- Localization updates.

## 3.13.1 ##
- GlobalContribs: cleaner display of IP addresses within ranges.

## 3.13.0 ##
- (T279041) Add support for IP ranges (except for Pages Created tool).
- (T235941) Support shorthand syntax for IPv6 addresses.
- GlobalContribs: add useful external links, show IPs within ranges, and indicate
  which edits have been reverted.
- Localization updates.

## 3.12.1 ##
- Localization updataes.

## 3.12.0 ##
- Support for third-party installations officially dropped due to lack of interest.
- (T276867) Move all documentation to https://www.mediawiki.org/wiki/XTools
- AdminStats (T279862): fix name of 'Flagged Revisions' extension.
- (T244799): Redirect back to previous page after logging in.
- Fix rate limiting; now uses XFF header rather than session ID.
- (T224382) Some improvements to session persistence.
- Remove About page.
- Various other minor bug fixes.
- Bump dependencies.
- Localization updates.

## 3.11.2 ##
- Improvements to dark mode styles.
- Localization updates.

## 3.11.1 ##
- Pages: Fix when 'all' namespace is requested but only mainspace results exist.
- TopEdits: Fix pagination for single namespace view.
- Add dark mode styles for clients with `prefers-color-scheme: dark`.
- Localization updates.

## 3.11.0 ##
- (T273988): Add support for new replicas redesign.
- Upgrade to Symfony 4.4
- Refactor the 'offset' parameter to accept a timestamp.
- AutoEdits: Add pagination to the API.
- (T257416): Fix column sorting when working with non-numeric values.
- AdminStats (T234777): Don't show total if only one action available.
- AdminStats (T276190): Fix number of days calculation.
- Various code quality improvements.
- Removed deprecated response values from SimpleEditCount API.
- Localization updates.

## 3.10.19 ##
- AutoEdits: Make the link to request new tools go to the main talk page
  instead of the preloaded form.

## 3.10.18 ##
- AutoEdits (T262631): Move configuration to on-wiki JSON page.
- TopEdits: Fix bug with mainspace pages containing a colon in the title.
- Localization updates.

## 3.10.17 ##
- AutoEdits (T262325, T217505, T262147): Updates to Twinkle definitions
- AutoEdits (T250615): add TranslationAdder for en.wikisource
- AutoEdits (T261085): Fix typo Commonsist → Commonist
- AutoEdits (T262125): Add additional PAWS tag
- AutoEdits (T262124): Add more CropTool tags
- Fixes to Toolforge URLs.
- Localization updates.

## 3.10.16 ##
- UserRights (T256546): handle 'wikidata' alias when parsing InitialiseSettings
- AutoEdits (T256167): Add ru entry for page moves
- AutoEdits (T253566): Add uk entry for AutoWikiBrowser
- AutoEdits (T250613): Make CropTool and JWB global
- AutoEdits (T253275): Add RedWarn to en.wikipedia
- AutoEdits (T255308, T250008): Add Popups and remove.js to ru.wikipedia
- AutoEdits (T250637): Fixes to AjaxQuickDelete for commons
- AutoEdits (T246775): Add ImageAnnotator to commons
- Localization updates.

## 3.10.15 ##
- ArticleInfo (T250951): Update query for wikidata bugs following
  removal of the wb_terms table.
- AutoEdits (T248077): Add 'StubSorter' to enwiki config.
- GlobalContribs: bug fix for when user has no live edits.
- Localization updates.

## 3.10.14 ##
- GlobalContribs (T246522): only query when actor ID exists
- AutoEdits (T243147): fix to 'The Distributed Game' definition
- AutoEdits (T243640): Add tag to flickr2commons entry
- AutoEdits (T243638): Add 'IA Upload' tool for commons.wikimedia
- AutoEdits (T243636): Add 'FileImporter' to commons.wikimedia
- AutoEdits (T243637): add 'video2commons' for commons.wikimedia
- AutoEdits (T243630): add 'EasyResolve' for meta.wikimedia
- AutoEdits (T236987): add AC/DC, simpleSVGcheck, SVGEdit for commons.wikimedia
- AutoEdits (T245587): Add OpenRefine to www.wikidata.org
- AutoEdits (T245708): Add regex for Archer to Capricorn config
- Localization updates.

## 3.10.13 ##
- ArticleInfo (T243225): ensure proper encoding on links to wiki.
- AutoEdits (T243237): Allow tool definitions to target multiple tags.
- AutoEdits (T243142, T243182, T243153, T243145, T243147, T243141, T243140):
  Add several tools to configuration, along with some minor cleanup.
- Localization updates.

## 3.10.12 ##
- Pages (T242930): Fix handling of 'redirects' and 'deleted' parameters.
- ArticleInfo: Sync enwiki assessment colours with w:en:Template:Class/colour

## 3.10.11 ##
- Pages: Fix production error where routing params were missing.
- EditCounter: Fix routing to Top Edits API and the rights changes list.
- EditCounter (T241539): Show all 24 hours in timecard and include average edits.
- ArticleInfo (T241496): Merge current bots into former bots, not the opposite.
- Pages (T229578): Show total and average size of all pages.
- ArticleInfo: Make colours for minor/IP edits consistent.
- Various code refactoring and cleanup.
- Localization updates.

## 3.10.10 ##
- Authorship, Blame (T240283, T208086): Allow page titles with slashes.
- AdminStats (T241311): Fix sorting of user groups.
- T227359: Add links to 'User has not opted in...' message.
- TopEdits: Remove old /api/topedits endpoint, replaced with /api/top_edits.
- Minor aesthetic fixes to alert banners.
- Various code refactoring and cleanup.
- Log possible web crawlers (eventually may automatically be blocked).
- Add noindex,nofollow directives for result pages.
- Localization updates.

## 3.10.9 ##
- ArticleInfo: Put 'Authorship' section before 'Top editors'.
- ArticleInfo (T232693): Fix sorting for 'atbe' column.
- AutoEdits (T240096): Update short description tool.
- AutoEdits (T240214): Update config for Rater tool.
- AdminStats (T240090): Fix display of user group icons.
- Security updates (T240074)
- Localization updates.

## 3.10.8 ##
- AutoEdits (T222323): Add ns:1 for enwiki page curation
- AutoEdits (T236715): Add twinkle for simplewiki
- AutoEdits (T236716): Add change status for enwikisource
- AutoEdits (T236716): Restrict change status to ns 104
- AutoEdits (T236726): Update STiki definition
- AutoEdits (T237217): Update page curation
- AutoEdits (T237174): Add userRightsManager for enwiki
- AutoEdits (T237076): Add Replacer for eswiki
- AutoEdits (T231709): Expand wikidata merge
- AutoEdits (T236719): Combine commons global replace tools
- AutoEdits (T237458): Update Sagittarius to Capricorn
- AutoEdits (T237146): Add DisamAssist tool on es.wiki
- Update PHP dependencies and fix deprecations.
- Updates to documentation.
- Various code refactoring and cleanup.
- Localization updates.

## 3.10.7 ##
- EditCounter (T236095): Make month/year counts CSV in comprehensible format.
- AutoEdits (T235726): Add BDCS tool to enwiki config.
- AutoEdits (T230634): Add labelLister to wikidata config.
- AutoEdits (T230925): Fix delsort regex to pick up FWDS tool.
- AutoEdits (T233354): Add typo fixer tool to fawiki config.
- AutoEdits (T233732): Add SWViewer to global config.
- Meta: Require Y-m-d date format in routing.
- Localization updates.

## 3.10.6 ##
- EditCounter (T232804): Remove pages deleted from pages section.
- Update dependencies.
- Add links to download results as JSON, where applicable.
- Localization updates.

## 3.10.5 ##
- EditSummary (T202552): Add date filtering.
- AutoEdits (T226231): Make admin actions global.
- AutoEdits (T229555): Add 'Sitelink auto-change' to Wikidata config.
- AutoEdits (T229564): Add 'Sitelink auto-removal' to Wikidata config.
- AutoEdits (T229562): Add 'AutoEdit' to Wikidata config.
- AutoEdits (T223350): Add 'Merge.js' to Wikidata config.
- AutoEdits (T229563): Fix global definition for Cat-a-lot.
- EditCounter: Remove 'Global contributions' as subtool.
- Fix long-standing typo: Resonator -> Reasonator.
- Localization updates.

## 3.10.4 ##
- AutoEdits (T229387): Remove single_tag logic.
- TopEdits (T202552): Add date filtering options.
- AdminStats: add options export as wikitext, CSV and TSV.
- EditCounter: fix boolean logic for showing rights changes section.
- ArticleInfo: code refactoring and cleanup.
- AutoEdits: Expand tool definitions for Commons.
- Localization updates.

## 3.10.3 ##
- Blame: improve algorithm, allowing partial matches.
- EditCounter (T177903): Show files renamed.
- EditCounter (T226228): Don't show file uploads/renames for Commons.
  when Commons is the requested project.
- ArticleInfo: fix display of Authorship section.
- Localization updates.

## 3.10.2 ##
- Blame: remove BlameProjectPage route causing redirect loop.

## 3.10.1 ##
- Blame: Require query parameter before redirecting to result page.

## 3.10.0 ##
- Blame: Revive old Blame tool, using the WikiWho service.
- Authorship: Validate project is supported by WikiWho.
- Localization updates.

## 3.9.1 ##
- ArticleInfo (T226299): Add legacy route to articleinfo-authorship.

## 3.9.0 ##
- New top navigation with improved organization and links to sub-tools.
- Move EditCounter's latest global edits to dedicated tool. Add namespace
  and date range filters.
- Move ArticleInfo's Authorship to dedicated tool. Add options to show
  attribution stats given revision ID or date.
- AutoEdits: Add more tools, fixes to existing tools, and simply regular
  expressions to reduce the size of the SQL query.
- ArticleInfo: make top 10 by added text chart match percentages.
- EditCounter (T225389): Link and put 'Pages moved' under 'Actions'.
- Increase width of labels in forms to better accommodate translations.
- Record usage of ArticleInfo API.
- Handle errors when querying WikiWho API.
- Improve caching of Edit Counter and user-related queries.
- Various code cleanup and frontend styling tweaks.
- Localization updates.

## 3.8.1 ##
- EditCounter: Fix global edits subtool, add new /global-contribs route.
- EditCounter: other performance improvements to global edits.
- TopEdits: Improve performance of per-page query.
- Use new specialized sub-views for comment and actor tables.
- Fix bug where exception is thrown when start date is invalid.
- EditCounter (T225058): Fix counting of pending changes approval.
- Localization updates.

## 3.8.0 ##
- T223667: Implement actor storage, with minor refactoring to Repositories.
- EditCounter: Add APIs for log counts, namespace totals, month counts, and
  timecard.
- EditCounter: Remove obsolete internal API endpoints.
- EditCounter: Speed up loading of the Namespace Totals sub-tool.
- Pages: Don't include null values type-casted to zero in API responses.
- SimpleEditCounter: Show system edit count for users with > 50000 edits.
- SimpleEditCounter: Deprecate camelCased keys of API response.
- AdminStats: remove deprecated adminstats/{project}/{days} API endpoint.
- Fix exception thrown when giving wiki parser a null value.
- Prettify filenames for CSV and TSV exports.
- Various frontend fixes and tweaks.
- Expand API documentation and fix errors.
- Localization updates.

## 3.7.10 ##
- Hotfix for checking app.wikiwho config parameters.
- Fix checking of controller action in RateLimitSubscriber.

## 3.7.9 ##
- ArticleInfo: Major performance improvements, bug fixes and code cleanup.
- ArticleInfo: Login to WikiWho API to get around throttling.
- AdminStats: Fix bug for when invalid log types are requested.
- T222920: Fix unsetting of 'all' and 'none' tool types in AutoEdits.
- Localization updates.

## 3.7.8 ##
- Hotfix for request blacklist functionality
- ArticleInfo: Cache queries and increase memory limit for authorship action.
- EditCounter (T222552): Change 'approve' log to count manual approvals.
- EditCounter (T218465): Add history merge log count.
- AutoEdits: Cleanup of configuration file, expand namespace definitions
- Localization updates

## 3.7.7 ##
- Improve request blacklist functionality, allowing combination of user agent,
  referer and URI.
- Allow AJAX on wikify and recordUsage API endpoints.

## 3.7.6 ##
- Disallow scraping XTools with JavaScript, informing the client to use the API
- AdminStats: Only allow meta.wikimedia for Steward Stats
- ArticleInfo: Make authorship route accept page title as query string
- AutoEdits (T222323): Optimize by specifying applicable namespaces for some tools.
- AutoEdits (T222134): Add undo-last-edit to enwiki config.
- Updates to API documentation.
- Localization updates

## 3.7.5 ##
- AutoEdits: Fix exception sometimes thrown when querying APIs.
- ArticleInfo: Updates and fixes to enwiki's assessment icons.
- ArticleInfo: Fix exception thrown when processing large number of revisions.
- Change 'WebChat' link text to 'connect' for clarity.

## 3.7.4 ##
- AutoEdits (T221727): add QuickStatements to wikidata config.
- EditCounter (T222049): Fix links to AbuseFilter log.
- ArticleInfo: add 'n others' slice to authorship chart, adjust colours,
  and limit subrequest view to 500 results.
- Localization updates.

## 3.7.3 ##
- ArticleInfo: fix first/latest edit datestamps.
- EditCounter: fix Timecard section definition.
- T221352: Add RequestDeletion to wikidata AutoEdits config.
- T212927: Add Script Installer to enwiki AutoEdits config.
- T213019: Add Shortdesc helper to enwiki AutoEdits config.
- T212925: Add reply-link to enwiki AutoEdits config.
- T214005: Add effp-helper to enwiki AutoEdits config.
- T217091: Improve Cat-a-Lot config in AutoEdits.
- Fix formatting of copyright year in footer.

## 3.7.2 ##
- AdminStats: Add account locks to Steward Stats config.
- AdminStats: Include gblock2 log action as part of global blocks
  in Steward Stats.

## 3.7.1 ##
- T213119: Indicate CheckUsers, bots and AbuseFilter managers in AdminStats.
- T193888: Indicate global sysops in Admin Stats.
- Fix translation error in bg.json causing fatal runtime Twig exception.

## 3.7.0 ##
- Update to Symfony 4.2, fix deprecations, rework directory structure.
- T171992, T185274: Add new "Patroller Stats" and "Steward Stats" tools,
  integrated with Admin Stats, with form to selectively choose log actions.
- AdminStats: use icons to indicate user groups.
- T193888: Indicate stewards in Admin Stats.
- T205652: Allow any start/end date for the Admin Stats API (max one month).
- T213449: Include AbuseFilter changes in Admin Stats.
- T218390: Fix live/deleted pie chart in Edit Counter.
- T212985: Add namespace definitions to AutoEdits config to speed up queries.
- T214733: Fix deletion summary in Pages Created tool.
- T213503: Localize numerals of Kurdish language.
- EditCounter: skip projects that aren't available on the replicas.
- TopEdits: Allow 'limit' URL parameter in per-namespace results.
- Remove temporary rate limiting code.
- Various code cleanup, fixing deprecations, and refactoring.
- Localization updates.

## 3.6.22 ##
- T214137: Add Enterprisey's AFCRHS to AutoEdits configuration.
- T212984: Add Sagittarius fork to AutoEdits configuration.
- T213789: Add reFill 2 to reFill AutoEdits configuration.
- AutoEdits: Use full list of tools on AutoEdits contribs pages.
- EditCounter: Skip over wikis missing from replicas in Global edit counts.
- ArticleInfo: fix prod error thrown when checking Main Page.
- Minor improvements to cross-browser support.
- Fix double logging of errors in production.
- Localization updates.

## 3.6.21 ##
- Show large message saying you can login to avoid rate limiting.
- Increase timeout of loading contribs pages to 60 seconds.
- Fix bug with checking UA in RateLimitSubscriber.
- Update copyright year.
- Localization updates.

## 3.6.20 ##
- Rate limit all requests by session ID.
- Include host, URI and UA in error reports.
- ArticleInfo: fix return type that was causing critical prod errors.
- Localization updates.

## 3.6.19 ##
- Temporary user-specific throttling. This release should only be used
  on the Wikimedia Foundation installation of XTools.

## 3.6.18 ##
- Email critical errors to maintainers.
- T212025: Add alternate tools for OneClickArchiver in AutoEdits.
- T211172: Fix link for YABBR tool in AutoEdits.
- T212026: Fix link for Page Curation tool in AutoEdits.
- T211934: Add deOrphan tool to AutoEdits.
- Localization updates.

## 3.6.17 ##
- Restore "Add date filtering options to Pages tool" with bug fixed.

## 3.6.16 ##
- Revert "Add date filtering options to Pages tool"

## 3.6.15 ##
- T202552: Add date filtering options to Pages tool.
- T211172: Add YABBR to enwiki AutoEdits configuration.
- T211137: Add regex for German use of AWB to AutoEdits.
- AutoEdits: add option to show edits made only with given tool.
- Remove all slash routes from code, which are automatically supported
  in Symfony 4.
- Localization updates.

## 3.6.14 ##
- T201850: Query logging_logindex instead of userindex for performance.
- AdminStats: Fix a bug for when there are no admins.
- AdminStats: Indicate interface admins.
- T210314: Add stubsearch tool to AutoEdits enwiki configuration.
- T210938: Fix a broken tool link in AutoEdits.
- AutoEdits: Improve pt translation and fix some links.
- Localization updates.

## 3.6.12 ##
- T189234: Use new comment table for fetching edit/log summaries.
- EditCounter: Remove edit summary and semi-automated stats, instead
  providing links to the dedicated tools. This is for performance reasons.
- EditCounter: Move 'Edits' section of General Stats to the top-right.
- EditCounter: invalidate auto-removals of rights when expiry changed.
- EditCounter: handle scenario where user rights log entry was deleted.
- Whitelist MetaController::recordUsage from rate limiting.
- Localization updates.

## 3.6.11 ##
- T189234: Update relevant queries to use new comment table.
- Fix checking of previously entered project on index forms.

## 3.6.10 ##
- Add temporary notice about missing log/edit summaries.
- Localization updates.

## 3.6.9 ##
- ArticleInfo: handle scenario where there are no edits >0 bytes in size.
- ArticleInfo: Treat interwiki redirects as a nonexistent page.
- ArticleInfo: fix ArticleInfo::getUsernameMap() to handle empty array of IDs.
- Handle error when given an ivalid login token.
- Localization updates.

## 3.6.8 ##
- Fix a few lingering production errors after being put in strict mode.

## 3.6.7 ##
- ArticleInfo: fix bug where layout Twig macro was not imported
- EditCounter: make sure log_params are a string

## 3.6.6 ##
- EditCounter: fix bug in rightschanges where new user groups is null

## 3.6.5 ##
- Production hotfix: Allow user session getter to return object, array or null.

## 3.6.4 ##
- Production hotfix: handle newly created pages that aren't yet in the replicas
  in the ArticleInfo API endpoint.

## 3.6.3 ##
- Production hotfix: ensure AppExtension::loggedInUser() returns ?array

## 3.6.1 ##
- Production hotfix: only pass strings to AutomatedEditsHelper::getTool().

## 3.6.0 ##
- T188699: Upgrade to Symfony 4.1; directory restructure and removal of unused
  bundles. Put all classes in PHP 7.2 strict mode.
- T199839: update queries in AutoEdits to use new change tag schema
- T205655: Change 'admin' heading in the user rights low to 'performer'.
- Various other bug fixes and code cleanup.
- Log fatal errors to dedicated file.
- Localization updates.

## 3.5.0 ##
- Upgrade to PHP 7.2, set as the minimum requirement.
- Use Webpack for asset management.
- Remove unused code, fonts, images, and other files.
- Add project routes with slashes at the end for convenience.
- AdminStats: set default range to 31 days instead of 30.
- T204635: Add Snuggle for en.wikipedia to AutoEdits config.
- T205182: Upgrade Intuition, adding some missing language labels.
- Localization updates.

## 3.4.5 ##
- ArticleInfo: reverts 3.4.4 release. Issue with externallinks table has
  been resolved.
- TopEdits: Fix routing for per-page view.

## 3.4.4 ##
- ArticleInfo: hotfix, externallinks table is no longer accessible.

## 3.4.3 ##
- T199765: Show up to 1,000 results when viewing a single namespace in
  TopEdits, and provide pagination.
- TopEdits: Remove edit count restriction when view edits to single pages.
- AutoEdits: Add tag for AWB edits. Show warning that all edits made by
  bots may be automated.
- T203518: Show log entry deletions under admin actions in Edit Counter.
- T202141: Include disclaimer that deleted pages may have been redirects
  in the Pages tool.
- ArticleInfo: Add 'all data is approximate' disclaimer to Top Editors
  section. Add missing X icon to toggle table in Authorship section.
- Fix use of ltrim to remove duplicate namespace from page titles.
- Localization updates.

## 3.4.2 ##
- T202836: Show pending automatic rights changes in the Edit Counter,
  and fix bug where these were being counted as the current rights.
- AutoEdits: Fix silent JavaScript error on index page.
- Quote: Allow production API access, but leaving the HTML tool disabled.
- Update dependencies and various code cleanup.
- Localization updates.

## 3.4.1 ##
- Fix bug in ExceptionListener where production errors werne't showing
  the formatted production error page.

## 3.4.0 ##
- EditCounter: allow users to choose which statistics to show.
- EditCounter: use cookies to store user's preferred sections.
- CategoryEdits: include number of unique pages edited.
- T200791: (AutoEdits) refine detection of AWB edits.
- T193481: (EditCounter) make namespace breakdown of month/year counts
  more accessible.
- T186433: (ArticleInfo) render CheckWiki notices as HTML.
- EditCounter: show legend for year/month chart if viewing directly.
- EditCounter: Add index route for subtools (/ec-rightschanges, etc.).
- Save 'project' parameter in a cookie so it is sticky.
- ArticleInfo: add download links for Top Editors and Authorship sections.
  This also removes the 'Download as wikitext' link as it now lives in
  the 'Download' dropdown.
- Pages: Show assessment counts, and fix wikitext export.
- Major refactoring of controllers and models.
- Standardize API responses to always include requested parameters.
- Automatic validation of common parameters ('project', 'user', etc.),
  across all controller methods.
- Rename the 'article' parameter to the more appropriate name 'page'.
- AdminScore: refactor code to use the model/repository paradigm.
- Better support of pre-filling field in index forms from URL parameters.
- Update dependencies and fix Symfony deprecations.
- Merge ApiController to DefaultController.
- Add elapsed time to API responses.
- Typos, code aesthetics, and other minor bux fixes.

- Localization updates.

## 3.3.10 ##
- Localization updates.

## 3.3.9 ##
- ArticleInfo: Add documentation for new top_editors API endpoint.
  Some bug fixes around handling of request parameters.

## 3.3.8 ##
- T199922: Don't attribute autopromotions to user in user rights log.
- ArticleInfo: performance improvements to basic info and
  rev count queries.
- EditCounter: Remove edit count threshold for ec-rightschanges action.
- ArticleInfo: Add new Top Editors API endpoint.
- Allow the wiki table to be configured by parameter.
- Localization updates.

## 3.3.7 ##
- ArticleInfo: Include assessment in API endpoint.
- ArticleInfo: Show assessment in gadget, if available.
- ArticleInfo: Add 'links' API endpoint.
- Localization updates.

## 3.3.6 ##
- T189286: Show time of autoconfirmed promotion in Edit Counter.
- T197005: Show latest logged action in Edit Counter, and link to
  the log entry. Also link to first/latest edits.
- Show registration date in the Edit Counter.
- Localization updates.

## 3.3.5 ##
- T190956: Allow browsing through latest global edits.
- T195000: Fix querying of Wikidata errors.
- Update link to WikiWho service.
- Localization updates.

## 3.3.4 ##
- T191943: Add support for IPs across all applicable tools.
- T191942: Fix ArticleInfo bug, unique editors are overcounted.
- T192846: Add JWB to enwiki AutoEdits configuration.
- Add NA-level importance to enwiki assessments configuration.
- Fix links to Edit Counter following recent refactor.
- Localization updates.

## 3.3.3 ##
- Hotfix for bug in redirecting to index page when the user has
  too many edits.

## 3.3.2 ##
- Make all internal route names and i18n key names consistent.
- Refactor Page Assessments logic
- Add assessments configuration for en.wikivoyage, hu.wikipedia
  and fr.wikipedia.
- Complete en.wikipedia assessment configuration.
- Make API endpoint to get the full assessment configuration.
- Add API endpoint for bash quotes.
- T192629: Pass 'offset' parameter to download links in Pages.
- T192133: Fix pie charts in Category Edits summary section.
- Localization updates.

## 3.3.1 ##
- T184969: Add Turkish page assessments.
- T185023: Add Arabic page assessments.
- Show page assessments in non-mainspace, if assessment exists.
- Add API to get page assessments config and for given articles.
- Add Apple favicons, should reduce 404s in production logs.
- Localization updates.

## 3.3.0 ##
- T189645: New Category Edits tool to see edits made by a user to
  one or more categories.
- T191135: Handle Twig runtime exceptions and show original
  exception, if present.
- Set max_statement_time on individual database queries, and treat
  error 2013 (lost connection to MySQL server) as query timeout.
- T171278: EditCounter: show admin actions of former admins.
- EditCounter: localize names of user groups, show current and
  former user groups.
- T191136: AutoEdits: Add APC tool for ptwiki, better pt support.
- AutoEdits: add content translation tool and ProveIt. Use new
  'contribs' flag to show these tools in non-automated contribs.
- T191133: AutoEdits: improve showing of edits with tools that have
  a tag but may share tags with other tools.
- T180819: Use metawiki when logging in wiht OAuth.
- Remove old, unused multithreading code.
- TopEdits: Increase test coverage.
- Localization updates.

## 3.2.5 ##
- T190496: AdminStats: Make sure former admins are shown. Rework
  query to only show users with > 1 action. Don't count log actions
  that deleted pages via redirect, autopromotion of user rights, or
  moving protections.
- Allow sortable tables to re-fill the numbering of rank column.
- T190201: AdminScore: Fix negative day value.
- Localization updates.

## 3.2.4 ##
- ArticleInfo: Improve performance of ArticleInfo API endpoint.

## 3.2.3 ##
- AutoEdits: add better support for Korean wikis, kowiki specifically.
- EditCounter: Fix display of timestamps in rights changes section.
- Make sure i18n language fallback files are downloaded, but only if
  they exist.
- Localization updates.

## 3.2.2 ##
- AutoEdits: Fix automated edits query for when dealing with tags,
  and non-automated edits query for when there are multiple tags.

## 3.2.1 ##
- Hotfix - Don't autowire I18nHelper.

## 3.2.0 ##
- T185908: Numbers and dates localization across all tools.
- Greatly improved the RTL interface.
- Moving all i18n logic to a I18nHelper service.
- AutoEdits: Major refactor. Moved contribution list to a dedicated
  subrequest page.
- AutoEdits: introducing the new 'autoedits-contributions' tool
  to view edits using (semi-)automated tools. This includes a
  new API endpoint: /api/user/automated_tools/{project} that gives
  you the full list of known (semi-)automated tools on the project.
- AutoEdits: Cleaner API error responses.
- Add "See full statistics" links to the top of every subrequest
  page of a tool to navigate back to the full results.
- ArticleInfo: make size of textshares pie chart relative to the
  number of entries in the adjacent table.
- Localization updates.

## 3.1.45 ##
- T188603: Include link to export to PagePile in Pages tool.

## 3.1.44 ##
- Pages: add export options for wikitext, CSV, TSV and JSON.
- Pages: hover over 'deleted' text to reveal deletion summary.
- T165864: Check for recreated pages in Pages tool and label them.

## 3.1.43 ##
- AutoEdits: allow defining rules per-language, and add some rules
  for German and Arabic. This should add support for many more wikis.
- AutoEdits: localize labels of the tools.
- AutoEdits: add some wiki-specific rules for dewiki, dewiktionary,
  and a few more for enwiki.
- AutoEdits: New API endpoint to get a raw list of known tools used
  on a given wiki.
- AutoEdits: better description, and make pie chart relative to the
  size of the number of tools to save real estate.
- Log database-level errors for easier debugging in production.
- Localization updates.

## 3.1.42 ##
- T178055: More support for screen readers.
- Add 'Feedback' link in footer, shorten links.
- Better AWB and Undo detection in AutoEdits.

## 3.1.41 ##
- Handle exception thrown in production when user has no edits.
- Fix broken TopEdits API endpoint.
- Fix broken EditSummary API endpoint.
- Minor styling fixes.

## 3.1.40 ##
- T178055: Improved screen reader support on the Edit Counter.
- Edit Counter rights changes now looks for local changes that
  were made on Meta, and also includes global rights changes.
- Auto-link raw URLs in edit summaries.
- Show notice that data could be inaccurate when viewing
  ArticleInfo on a very old page.
- Localization updates.

## 3.1.39 ##
- T187100: Fix issue with routing 'redirects' parameter in the
  Pages Created tool.
- Localization updates.

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
