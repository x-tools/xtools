# Release Notes #

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
