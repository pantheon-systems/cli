# Change Log
All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org)

## 3.6.1 - 2024-12-04

### Added

- New "branch" option to "local:clone" command (#2648)

### Fixed

- Bug while handling exception due to environment unable to wake (#2637)
- Added correct documentation link to env:clone-content command (#2632)
- Fixed undefined notice for $command_summary during WP-CLI/Drush execution (#2614)

## 3.6.0 - 2024-09-18

### Added

- New command "site:label:set" to set a site's label (#2626)
- Track command success rates and execution time (#2623)

## 3.5.2 - 2024-08-19

### Fixed

- Fix undefined notice from sftp password cleanup (#2609)
- Use right message after site or environment creation (#2611)

### Added

- Add new header x-pantheon-terminus-environment (#2613)

### Changed

- Add retries to env:wake (#2612)
- Add more logging to env:wake command (#2586)


## 3.5.1 - 2024-06-13
- Fix a bug where the --filter option was not working correctly for several commands [#2607]

## 3.5.0
- Retry option added for Drush and WP-CLI commands (#2588)
- Provides a trace ID when running remote commands (Drush, WP-CLI) (#2589)
- Terminus Workflow:waitForCommit command added (#2591)
- Add new static cache for site_names (#2594)
- Env deploy command should only be used in test/live envs (#2575)
- Fix multidev:create command help (#2579)
- On April 30st, 2024, Pantheon deprecated support for password authentication for ssh, which is used for git and sftp.  Going forward, users need to use ssh keys for git and sftp. [https://docs.pantheon.io/ssh-keys](https://docs.pantheon.io/ssh-keys) (#2572)
- Non-interactive commands should also exit with error if the workflow failed (#2583)

## 3.4.0

- Update clone:content command documentation #2560
- New test for env:metrics command #2562
- Handle error processing workflow in connection:set command. #2563
- The site:create command now waits for a site to be fully awake before completing #2564
- The multidev:create command now waits for the environment to be fully awake before completing #2565

## 3.3.5 - 2024-02-28

- Remove the concept of change_management in site:team:role commands [#2550](https://github.com/pantheon-systems/terminus/pull/2550)
- Add art:list command [#2554](https://github.com/pantheon-systems/terminus/pull/2554)
- Run Terminus tests against PantheonAPI [#2547](https://github.com/pantheon-systems/terminus/pull/2547)

## 3.3.4 - 2024-02-27

### Changed

- Added `--all` option to workflow:list to get all of the available workflows instead of last 100 (#2543)

## 3.3.3 - 2024-01-11

### Fixed

- Undefined variable notice for defaultMaxRetries (#2529)

## 3.3.2 - 2024-01-11

### Fixed

- Fix fatal error on payment methods (#2522)

### Changed

- Avoid hitting APIs so hard (#2523)

## 3.3.1 - 2023-11-30

- No changes, just retagged because of improper version numbering.

## 3.3.0 - 2023-11-29

- PHP 8.3 compatibility (#2555)
- Misc 8.2 deprecations


## 3.2.2 - 2023-09-28

### Fixed

- Return empty string on zero dates (#2494)
- Catch down network exception and non-object responses in fetch (#2492)

### Added

- New org:info command (#2472)
- Commands trace metadata (#2461)

### Changed

- Add retry and delay options for env:wake command (#2498)
- More specific description in env:code-rebuild (#2488)


## 3.2.1 - 2023-06-01

### Fixed
- Fix bug where `env:clone-content` command was not working Drupal sites (#2469)
- Fix bug where `env:clone-content` command was not working WordPress sites (#2471)
- Fix passthrough exit code from drush and wp-cli (#2470)

## 3.2.0 - 2023-05-22

### Added

- New env:rebuild-code command (#2417)
- Validation for aliases command (#2447)

### Fixed
- Bug with multidev:delete when ids are numbers-only (#2462)

### Changed
- Add upstream label field to site:info (#2455)
- Do not check for user membership to remove supporting org (#2453)
- Improve error messages on error 5xx (#2454)
- Add search and replace to env:clone-content command for WordPress sites (#2448)
- Deprecate getSite with param (#2449)

## 3.1.5 - 2023-04-06

### Added
- New env:rotate-random-seed command (#2444)

## 3.1.4 - 2023-03-01

### Added
- Multiple tag filter support for org:site:list command (#2360)
- Workflow wait command added as a copy of existing command in Build Tools plugin (#2435)

### Fixed
- Cache clear option is now used in the env:deploy command (#2432)

### Changed

- PHP compatibility errors are written to stderr instead of stdout (#2434)
- Description for database import command (#2436)
- Description for workflow:info:logs command (#2439)
- Description for aliases command (#2438)

## 3.1.3 - 2023-02-01

### Added
- Add SFTP Port as an available field for connection:info (#2420)

### Fixed
- Fix env var for db name in connection:info (#2427)
- Library updates to fix compatibility issues with php 8.2 (#2430)

## 3.1.2 - 2023-01-03

### Added
- PHP 8.2 compatibility (#2414)

### Changed
- Documentation for import:site command (#2410)
- Database and cache connection information is now fetched from env vars (#2403)

## 3.1.1 - 2022-11-08

### Changed
- Update plan:set docs to sku instead of id (#2407)

### Fixed
- Remove Kint from terminus (#2405)
- Site:org:add should not need user org membership if a label is provided (#2404)
- Look for status code in ssh-key:add response (#2406)

## 3.1.0 - 2022-10-17

### Added
- Added new cache-hit/miss ratio in the cache commands (#2399)

### Fixed
- Fix deprecation notice in dependency on PHP 8.1 (#2397)

## 3.0.9 - 2022-10-06

### Changed
- Verbose option description to make it clearer (#2383)

### Fixed
- Terminus plugin manager composer commands should not be affected by current directory (#2386)
- Omit reload warning if running reload command (#2388)
- Deprecation warnings on PHP 8.1 (#2391)

## 3.0.8 - 2022-08-22

### Changed
- Install plugins can now be done from git url (#2362)

### Fixed
- Make sure local:clone gets the right branch (#2375)
- Plugin update should work without args or with 'all' args (#2369)
- Make backup:restore command work with element = all (#2381)

## 3.0.7 - 2022-03-29

### Changed
- Change alias for local:commitAndPush to lcp to avoid aliases conflict (#2341)
- Make framework value more human friendly for site:info command (#2351)

### Fixed
- Check upstream updates before getting upstream status to get composer updates info (#2349)
- Fix bug that didn't allow merging a multidev env with only numbers in the name (#2350)

## 3.0.6 - 2022-02-18

### Changed
- Make upstream status report on composer updates (#2338)

### Fixed
- Fix bug where `self:console` was not working because psy/psysh was a dev dependency. (#2332)
- Pin to league/container ^3 to avoid fatal errors (#2337)

## 3.0.5 - 2022-02-08

### Added
- Added the `--element` usage for `backup:create` (#2317)
- Add GitHub Pull Request Template and move Issue Template (#2325)

### Changed
- Replace git:// urls with https:// urls (#2319)

### Fixed
- Fix bug where `site:list` was unable to properly filter by tags when part of site team (#2316)
- Fix bug where `--simulate` option was being ignored (#2321)
- Fix bug where `site:delete` command always fails (#2324)
- Fix bug where `self:update` reports it only works with the phar version of Terminus (#2328)

## 3.0.4 - 2022-01-18

### Added
- Added self:plugin:migrate command (#2299)
- Add php 8.1 support (#2295)

### Changed
- Set memory limit with ini_set() rather than /usr/bin/env -S (#2303)
- Optimize Terminus phar build (#2307)
- Do not require composer/composer as a direct dependency of Terminus (#2308)
- Avoid dependency on ext-curl in Terminus (#2310)
- Improve error verbosity in `Sites::get()` (#2311)
- Retry API request on "Connection refused" and "Operation timed out" errors (#2313)

### Fixed
- self:update command should now work when plugins have been installed (#2297)
- Fix filestore scandir problem (#2309)

## 3.0.3 - 2021-12-17

### Fixed
- Fix PHP 7.4 regressions and revert PHP 8.1 support. PHP 8.1 support will be restored in a future release (#2292)

## 3.0.2 - 2021-12-17

### Added
- Add aliases for Terminus plugin commands (#2274)
- Add PHP 8.1 support (#2284)

### Changed
- Allow upstream:updates:apply to default to dev environment if no environment explicitly provided (#2282)

### Fixed
- Change env:wake to use https on the platform domain (#2283)
- Minor fixup to help output of dashboard:view and self:console (#2277)

## 3.0.1 - 2021-12-08

### Fixed
- Change file name in deploy script (#2270)

## 3.0.0 - 2021-12-08

### Added
- Look up host for 'terminus drush' / 'terminus wp' commands via an alternate nameserver, if selected (#2253)
- Typehint param and return value in lookupHostViaAlternateNameserver (#2253)
- Symfony Library updates (5.x)
- Adds plugin manager, contributed by [Ed Reel](https://github.com/uberhacker) (#2054)
    - New command `self:plugin:install` to install Terminus plugins.
    - New command `self:plugin:list` to list installed Terminus plugins.
    - New command `self:plugin:search` to locate Terminus plugins to install.
    - New command `self:plugin:uninstall` to uninstall Terminus plugins.
    - New command `self:plugin:update` to update already-installed Terminus plugins.

### Changed
- Update auth:login command so that to not produce a false negative on successful login (#2231)
- Update CODEOWNERS per 2.x branch (#2243)
- Update composer dependencies version variable in post-update script (#2239)
- Update phar builder and package dependencies (#2255)
- Show dependencies warning if folder does not exist (#2239)
- Implement cleanupOldDependenciesFolders function after running dependencies update (#2239)
- Update terminusPluginsDependenciesVersion var comment (#2239)
- Update warning message text (#2239)
- Print warning only if terminus has plugins (#2239)
- Improve hasPlugins, factory, and cleanupOldDependenciesFolders functions (#2239)
- Return json_decode as array (#2239)
- Restore openUrl function from LocalMachineHelper (#2248)
- Draft debian linux packaging (#2255, #2238)
- Update version compatibility check and messaging (#2267)

### Removed
- Remove code related to D9ify (#2246)

### Fixed
- Fix SavedTokens::getData() on auth:login (#2231)
- Fix failing SSHKeyCommandsTest (#2231)
- Fix fatal error in Environment::cacheserverConnectionInfo() (#2242)
- Fix issue with Phar builder as well as DEB dependencies (#2255)
- Fix coding standards (#2239)

## 2.6.0 - 2021-06-04

### Added
- `daily-ttl` and `weekly-ttl` options have been added to `Backups::setBackupSchedule()`. (#2133)
- `keep-for` option has been added to `backup:automatic:schedule`. (#2133)
- `expiry` information added to the output of `backup:automatic:info`. (#2133)

## 2.5.0 - 2021-01-20
### Added
- `getUsername` added to `Binding` model to retrieve the username for a connection. (#2107)

### Changed
- `upstream:updates:apply` now applies Composer changes in addition to upstream changes. (#2089)
- Connection usernames are no longer "pantheon" but derived from API data. (#2107)

### Fixed
- Fixed issue where `plan:info` receives a 404 error. (#2098)

## 2.4.1 - 2020-09-08
### Changed
- The `DrushRCEditor` class has been renamed to `DrushRcClass`. (#2083)
- Upstreams can be ID'd by `label`s, `product_id`s and `machine_name`s in addition to `id`s. (#2086)

### Fixed
- The `DrushRCEditor` class no longer emits deprecation warnings. (#2083)
- `tag:add` now rejects empty tags. (#2085)

## 2.4.0 - 2020-06-19
### Added
- New option `--no-db` added to `multidev:create` which will skip the duplication of the database from the source environment. (#2050)
- New option `--no-files` added to `multidev:create` which will skip the duplication of files from the source environment. (#2050)
- New option `no-db` added to `Environments::create` which will skip the duplication of the database from the source environment. (#2050)
- New option `no-files` added to `Environments::create` which will skip the duplication of files from the source environment. (#2050)
- New method `Upstream::hasCode()` returns a bool indicating whether the environment has code or not. (#2056)

### Changed
- `Request::download($url, $target)` now accepts directories in addition to files as its `$target` parameter. (#2053)
- The `backup:get` command's `--to` option now accepts directories in addition to files. (#2053)
- Return type of `Redis::disable()` has changed to a `Workflow` model. (#2055)
- Return type of `Redis::enable()` has changed to a `Workflow` model. (#2055)
- Return type of `Solr::disable()` has changed to a `Workflow` model. (#2055)
- Return type of `Solr::enable()` has changed to a `Workflow` model. (#2055)
- Return type of `Environment::disableHttpsCertificate()` has changed to a `Workflow` model. (#2055)

### Removed
- Removed now-redundant `Backup::serializeWithURL()` function. Use `Backup::serialize()` instead. (#2042)
- Removed obsolete `Environment::convergeBindings()` method. (#2055)
- Removed obsolete `Site::converge()` method. (#2055)

### Fixed
- Drush script is no longer included when generating Drush aliases. (#2076)
- PHP notice is not emitted when using `upstream:updates:status` on an environment without code. (#2056)
- PHP notice is not emitted when using `UpstreamStatus::hasUpdates()` on an environment without code. (#2056)

## 2.3.0 - 2020-01-10
### Added
- New const `TERMINUS_HOST_CERT` added to permit use of SSL certificates while making requests. (#2024)

### Changed
- Terminus will now load plugin files ending in `Hook.php` in addition to those ending with `Command.php` (#2025)
- The role parameter in `org:people:add` is now being validated before sending the request. (#2033)
- The role parameter in `org:people:role` is now being validated before attempting the change. (#2033)
- The role parameter in `site:team:add` is now being validated before attempting the change. (#2033)
- The role parameter in `site:team:role` is now being validated before attempting the change. (#2033)
- `Request::download($url, $target)` now accepts directories in addition to files as its `$target` parameter. (#2053)
- The `backup:get` command's `--to` option now accepts directories in addition to files. (#2053)

### Deprecated
- Deprecated the `--cc` option on `env:deploy`. Please use `env:clear-cache` instead. (#2022)

### Fixed
- PHP Deprecated warnings seen in PHP ≥ 7.4.0 (#2043)

## 2.2.0 - 2019-09-26
### Added
- Added `primary` field to the output of `domain:list`. (#2011)
- Added `domain:primary:add` command to set a domain as primary, causing traffic to redirect to it. (#2011)
- Added `domain:primary:remove` command to remove a domain's primary designation. (#2011)

## 2.1.0 - 2019-09-03
### Added
- Added `--filter` option to `backup:list` command (#1992)
- Added `--filter` option to `branch:list` command (#1992)
- Added `--filter` option to `domain:list` command (#1992)
- Added `--filter` option to `env:list` command (#1992)
- Added `--filter` option to `multidev:list` command (#1992)
- Added `--filter` option to `org:list` command (#1992)
- Added `--filter` option to `org:people:list` command (#1992)
- Added `--filter` option to `org:site:list` command (#1992)
- Added `--filter` option to `org:upstream:list` command (#1992)
- Added `--filter` option to `payment-method:list` command (#1992)
- Added `--filter` option to `plan:list` command (#1992)
- Added `--filter` option to `site:list` command (#1992)
- Added `--filter` option to `site:org:list` command (#1992)
- Added `--filter` option to `site:team:list` command (#1992)
- Added `--filter` option to `ssh-key:list` command (#1992)
- Added `--filter` option to `upstream:list` command (#1992)
- Added `--filter` option to `upstream:updates:list` command (#1992)

### Deprecated
- `backup:list` `--element=VALUE` option is deprecated. Use `--filter="type=VALUE"` instead. (#1992)
- `site:list` `--framework=VALUE` option is deprecated. Use `--filter="framework=VALUE"` instead. (#1992)
- `site:list` `--name=VALUE` option is deprecated. Use `--filter="name=VALUE"` instead. (#1992)
- `site:list` `--plan=VALUE` option is deprecated. Use `--filter="plan_name=VALUE"` instead. (#1992)
- `org:site:list` `--plan=VALUE` option is deprecated. Use `--filter="plan_name=VALUE"` instead. (#1992)
- `org:site:list` `--tags=VALUE` option is deprecated. Use `--filter="tags=VALUE"` instead. (#1992)
- `org:upstream:list` `--framework=VALUE` option is deprecated. Use `--filter="framework=VALUE"` instead. (#1992)
- `org:upstream:list` `--name=VALUE` option is deprecated. Use `--filter="label=VALUE"` instead. (#1992)
- `upstream:list` `--framework=VALUE` option is deprecated. Use `--filter="framework=VALUE"` instead. (#1992)
- `upstream:list` `--name=VALUE` option is deprecated. Use `--filter="label=VALUE"` instead. (#1992)

### Obsolete
- 'pantheon-systems/terminus-aliases-plugin' ignored as load time, as its functionality is now provided in Terminus core. (#1994)
- PHP 5.5 no longer actively supported, as it is EOL, and no longer testable on Travis.

### Changed
- `drush:aliases` now produces both Drush 8 and Drush 9 aliases. Wildcard alias records always used (requires Drush 8.3.0 or later). (#1994)
- `site:info`'s value of the `region` field has been changed to use human-readable region names. (#1985)
- `site:list`'s value of the `region` field has been changed to use human-readable region names. (#1985)
- Owner parameter displayed in the success notice of  `owner:set` instead of the user's first and last names. (#2007)

### Removed
- Users' first and last names removed from the list provided by `site:team:list`. (#2007)
- Users' first and last names removed from the list provided by `org:people:list`. (#2007)

## 2.0.1 - 2019-04-28
### Fixed
- Fixed `site:org:list` so that it no longer ends without returning anything. (#1964)
- Fixed `env:deploy` so the `--note` option is used when initializing the test or live environments. (#1965)
- "Deploy from Terminus" is the default message used by `env:deploy` when initializing the test or live environments. (#1965)
- Fixed issue wherein any list command using datetime filters' first item was a formatted Unix datetime 0. (#1970)
- Fixed update message when running Terminus PHAR. (#1972)

## 2.0.0 - 2019-02-20
### Added
- New `plan:list` command lists the plans available to a site. (#1901)
- New `plan:set` command sets a site's plan. (#1901)
- New `Plans` collection interacts with plans available to a Site. (#1901)
- New `Plan` model represents a plan available to a Site or set on a site. (#1901)
- New `Site::getPlan()` function to retrieve a model representing the Site's present plan. (#1901)
- New `Site::getPlans()` function to retrieve a collection representing all available plans for the Site. (#1901)
- `backup:list` now emits a warning when its list is empty. (#1906)
- `branch:list` now emits a warning when its list is empty. (#1906)
- `domain:list` now emits a warning when its list is empty. (#1906)
- `env:list` now emits a warning when its list is empty. (#1906)
- `plan:list` now emits a warning when its list is empty. (#1906)
- `site:team:list` now emits a warning when its list is empty. (#1906)
- `upstream:list` now emits a warning when its list is empty. (#1906)
- A progress bar has been added to the workflow processing portion of `backup:restore`. (#1907)
- A progress bar has been added to the workflow processing portion of `connection:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `env:clear-cache`. (#1907)
- A progress bar has been added to the workflow processing portion of `env:clone-content`. (#1907)
- A progress bar has been added to the workflow processing portion of `env:commit`. (#1907)
- A progress bar has been added to the workflow processing portion of `env:deploy`. (#1907)
- A progress bar has been added to the workflow processing portion of `env:wipe`. (#1907)
- A progress bar has been added to the workflow processing portion of `https:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `https:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `import:complete`. (#1907)
- A progress bar has been added to the workflow processing portion of `import:database`. (#1907)
- A progress bar has been added to the workflow processing portion of `import:files`. (#1907)
- A progress bar has been added to the workflow processing portion of `import:site`. (#1907)
- A progress bar has been added to the workflow processing portion of `lock:disable`. (#1907)
- A progress bar has been added to the workflow processing portion of `lock:enable`. (#1907)
- A progress bar has been added to the workflow processing portion of `multidev:create`. (#1907)
- A progress bar has been added to the workflow processing portion of `multidev:delete`. (#1907)
- A progress bar has been added to the workflow processing portion of `multidev:merge-from-dev`. (#1907)
- A progress bar has been added to the workflow processing portion of `multidev:merge-to-dev`. (#1907)
- A progress bar has been added to the workflow processing portion of `new-relic:disable`. (#1907)
- A progress bar has been added to the workflow processing portion of `new-relic:enable`. (#1907)
- A progress bar has been added to the workflow processing portion of `org:people:add`. (#1907)
- A progress bar has been added to the workflow processing portion of `org:people:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `org:people:role`. (#1907)
- A progress bar has been added to the workflow processing portion of `org:site:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `owner:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `payment-method:add`. (#1907)
- A progress bar has been added to the workflow processing portion of `payment-method:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `plan:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `redis:disable`. (#1907)
- A progress bar has been added to the workflow processing portion of `redis:enable`. (#1907)
- A progress bar has been added to the workflow processing portion of `service-level:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:create`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:org:add`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:org:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:team:add`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:team:remove`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:team:role`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:upstream:clear-cache`. (#1907)
- A progress bar has been added to the workflow processing portion of `site:upstream:set`. (#1907)
- A progress bar has been added to the workflow processing portion of `solr:disable`. (#1907)
- A progress bar has been added to the workflow processing portion of `solr:enable`. (#1907)
- A progress bar has been added to the workflow processing portion of `upstream:updates:apply`. (#1907)
- New const `TERMINUS_TIMEOUT` added to extend the timeout maximum for remote commands. (#1908)
- A progress bar has been added to the process portion of `remote:drush`. (#1910)
- A progress bar has been added to the process portion of `remote:wp`. (#1910)
- A progress bar has been added to the workflow processing portion of `site:delete`. (#1922)
- Added the TerminusConfig::formatDatetime() function in order to use the configuration to format datetimes.  (#1923)
- Added the --region flag to `site:create`. (#1932)
- Add site region to site:info and site:list (#1933)
- Added options array parameter to `Environment::cloneDatabase` accepting `clear_cache` and `updatedb`. (#1940)
- Added options to `env:clone-content` accepting `cc` and `updatedb` both defaulting to false. (#1940)
- Added `--plan` option to `site:list` to filter the site list by plan name. (#1944)
- Added `--plan` option to `org:site:list` to filter the organizational site list by plan name. (#1944)
- Added `Sites::filterByPlanName(string)` function to filter the site models by their `plan_name` attribute. (#1944)
- Added `Environment::hasUncommittedChanges()` to determine whether SFTP-mode environments have changes which have not been committed. (#1948)
- Added `--upstream` option to `site:list` to filter the site list by their upstream UUID. (#1946)
- Added `--upstream` option to `org:site:list` to filter the organizational site list by their upstream UUID. (#1946)
- Added `Sites::filterByUpstream(string)` function to filter the site models by their `product_id` attribute. (#1946)
- Added `is_owner` field to the output of `site:team:list` in order to indicate which user is the site owner. (#1949)
- Added boolean `is_owner` field to the output of `SiteUserMemberships::serialize()` in order to indicate which user is the site owner. (#1949)
- Added `SiteUserMemberships::isOwner()` function in order to ascertain whether the user is the site's owner. (#1949)
- A `--progress` option has been added to `remote:drush` and `remote:wp` to enable progress for remote commands. (#1947)

### Changed
- `org:site:list` now displays a `Plan`/`plan_name` field to replace `Service Level`/`service_level`. (#1901)
- `site:info` now displays a `Plan`/`plan_name` field to replace `Service Level`/`service_level`. (#1901)
- `site:list` now displays a `Plan`/`plan_name` field to replace `Service Level`/`service_level`. (#1901)
- Collections' and Models' `$pretty_name` static property has become const `PRETTY_NAME`. (#1906)
- The empty-list notice on `org:people:list` has become a warning. (#1906)
- The empty-list notice on `org:site:list` has become a warning. (#1906)
- The empty-list notice on `payment-method:list` has become a warning. (#1906)
- The empty-list notice on `site:list` has become a warning. (#1906)
- The empty-list notice on `site:org:list` has become a warning. (#1906)
- Slashes are no longer escaped when converting the body of requests to JSON before cURL. (#1909)
- Moved the `sendCommandViaSsh` function from `Environment` to `SSHBaseCommand`. (#1910)
- Moved the `useTty` function from `SSHBaseCommand` to `LocalMachineHelper`. (#1910)
- `site:delete` now uses a workflow. (#1922)
- `Site::delete()` now returns a Workflow object. (#1922)
- `upstream:updates:list` now orders the pending updates in chronological order. (#1852)
- TerminusConfig::setSource() changed from public to now protected. (#1923)
- The `started_at` data returned by `workflow:list` is now formatted using TERMINUS_DATE_FORMAT. (#1923)
- The `finished_at` data returned by `workflow:list` is now formatted using TERMINUS_DATE_FORMAT. (#1923)
- Site::serialize() 'frozen' attribute has changed from string to boolean. (#1923)
- Site::serialize() 'created' attribute has changed to a Unix timestamp. (#1923)
- Site::serialize() 'last_frozen_at' attribute has changed to a Unix timestamp. (#1923)
- Environment::serialize() 'environment_created' attribute has changed to a Unix timestamp. (#1923)
- Environment::serialize() 'initialized' attribute has changed from string to boolean. (#1923)
- Environment::serialize() 'locked' attribute has changed from string to boolean. (#1923)
- Environment::serialize() 'onseverdev' attribute has changed from string to boolean. (#1923)
- Domain::serialize() 'deletable' attribute has changed from string to boolean. (#1923)
- Lock::serialize() 'locked' attribute has changed from string to boolean. (#1923)
- `Pantheon\Terminus\Friends\RowsOfFieldsTrait` has become `Pantheon\Terminus\Commands\StructuredDataTrait`. (#1923)
- `Backup::getUrl()` has been changed to `Backup::getArchiveURL()`. (#1923)
- Changed Environment::cloneDatabase() to accept an Environment object. (#1930)
- Changed Environment::cloneFiles() to accept an Environment object. (#1930)
- The target environment used in `env:clone-content` is now checked for initialization prior to cloning. (#1930)
- Updated Plans collection URL `accounts/site-account-forwarding/{site_id}/plans`. (#1936)
- `connection:set` will emit a warning if you are attempting to switch out of SFTP mode while the environment has uncommitted changes. (#1948)
- `connection:set` will ask for confirmation before switching out of SFTP mode with uncommitted changes as it destroys those changes. (#1948)
- `connection:set` will emit an error if the requested mode is invalid. (#1948)
- `Environment::changeConnectionMode(string)` never returns a string, only a Workflow. (#1948)
- `Environment::changeConnectionMode(string)` will throw a TerminusException if the mode is neither "git" nor "sftp". (#1948)
- `Environment::changeConnectionMode(string)` will throw a TerminusException if the requested mode is the current one. (#1948)
- The help text for `upstream:updates:status` now litanizes the possible results. (#1951)

### Deprecated
- `service-level:set` is now deprecated. Please use `plan:set`. (#1901)
- `Site::updateServiceLevel()` is now deprecated. Please use `Plans::set()`. (#1901)

### Fixed
- Fixed `Environment::importDatabase()` by switching from using the `import_database` workflow to `do_import`. (#1909)
- Fixed `Environment::importFiles()` by switching from using the `import_files` workflow to `do_import`. (#1909)
- Fixed `import:database` by switching from using the `import_database` workflow to `do_import`. (#1909)
- Fixed `import:files` by switching from using the `import_files` workflow to `do_import`. (#1909)
- Fixed `site:upstream:set` to appropriately reject attempted changes by unauthorized users. (#1913)
- Fixed `site:team:remove` when removing oneself from the team an error is no longer thrown upon success. (#1914)
- Fixed `TERMINUS_ENV` environment var. (#1917)
- Fixed `TERMINUS_SITE` environment var. (#1917)

### Removed
- Removed final, redundant 'Applied upstream updates to "dev"' notice from `upstream:updates:apply`. (#1851)
- Removed `TerminusConfig::fromArray()`. Use the inherited `TerminusConfig::combine()`. (#1923)
- Removed `TerminusConfig::toArray()`. Use the inherited `TerminusConfig::export()`. (#1923)
- Removed `Pantheon\Terminus\Friends\RowsOfFieldsInterface` (#1923)
- Removed deprecated `Workflow::wait` (#1937)
- Removed const `Workflow::POLLING_PERIOD`. Please use `TERMINUS_HTTP_RETRY_DELAY_MS` (#1937)
- Removed the often-inaccurate `php_version`/`PHP Version` info from the result of `site:info`. (#1952)
- Removed the often-inaccurate `php_version` property from the hash returned by `Site::serialize(). (#1952)
- Removed `Site::getPHPVersion()`. It is preferable to use `Environment::getPHPVersion()` for more accurate info. (#1952)

## 1.9.0 - 2018-09-11
### Added
- Added a `hide_git_mode_warning` option to disable the warning presented when users run Drush or WP-CLI commands on Pantheon sites that are in git mode.  (#1882)

### Fixed
- Prevent spurious dependency validation failures with Terminus plugins that have `dev` components named in their composer.lock file that have not been installed. (#1880)
- Removed the prompt displayed when running Drush or WP-CLI commands on a Pantheon server to avoid locking up auotmation scripts. (#1881)
- Set minimum PHP version to 5.5.38; some earlier versions of PHP 5.5 do not work with Terminus. (#1875)
- Fixed php warning when ssh key is missing its comment field. (#1843)

## 1.8.1 - 2018-06-08
### Fixed
- Fixed bug wherein messages that are passed in as arrays to TerminusException cause failure. (#1863)

## 1.8.0 - 2018-03-29
### Added
- `alpha:env:metrics` command has been added. (#1835)

### Fixed
- Prevented missing data about a site's upstream from preventing functioning of the SiteUpstream model. (#1833)

## 1.7.1 - 2018-02-27
### Fixed
- Fixed `workflow:watch` command by preventing cached data from being pulled. (#1827)

## 1.7.0 - 2018-01-29
### Added
- Added a `last_frozen_at` field to the output of `site:info` and the `Site::serialize()` function. (#1788)

## 1.6.1 - 2017-11-01
### Changed
- Added exponentially backing-off retries to HTTP requests. (#1782)

## 1.6.0 - 2017-10-11
### Added
- `domain:dns` has returns a new field, `detected_value`, which indicates the live DNS settings for a given domain. (#1756)
- `domain:dns` has returns a new field, `status`, which indicates whether live DNS settings for a given domain match the recommended setting. (#1756)
- Added new command `site:upstream:clear-cache` to clear code caches on a site. (#1762)
- `TerminusCollection::reset()` to reset the model array (after filtering or sorting). (#1779)

### Fixed
- Changed the Domains collection to use a newer API call, fixing `domain:dns`. (#1756)
- Fixed operations for sites that have been unfrozen. (#1766)

### Removed
- Removed the now-obsolete `Domains::setHydration(string)` function. (#1756)
- Removed `TerminusCollection::listing(string, string)`. (#1779)
- Removed the `Loadbalancer` model. (#1779)
- Removed the `Loadbalancers` collection. (#1779)

### Changed
- `multidev:create` is now checked for whether the site is frozen before attempting to execute. (#1761)
- `import:database` is now checked for whether the site is frozen before attempting to execute. (#1761)
- Checks for frozen sites will now throw errors on dev and multidev environments as well as test and live. (#1761)
- `domain:list` now lists `id`, `type`, and `deleteable` attributes. (#1763)
- `https:info` now lists `id`, `type`, `status`, `status_message`, and `deleteable` attributes. (#1763)
- `https:info` emits a `RowsOfFields` object instead of a `PropertyList`. (#1763)
- `domain:dns` now emits an info log instead of a warning. (#1772)
- `TerminusCollection::fetch()` no longer accepts an array of options. Use `setData(array)` to pass in data and `setFetchArgs(array)` for the same functionality. (#1779)

### Deprecated
- `Workflow::operations()` is deprecated. Use the `Workflow::getOperations()` to retrieve workflow operations. (#1769)

## 1.5.0 - 2017-08-17
### Changed
- Updated the name of the `longname` field output by `upstream:list` to `label`. (#1747)
- Updated the name of the `longname` field output by `upstream:info` to `label`. (#1747)
- Upstreams of types `core` and `custom` are the only ones which appear by default when using `upstream:list`. (#1747)
- The `--org` option of the `site:list` command now defaults to `"all"` instead of `null`, but its behavior is unchanged. (#1747)
- The `role` parameter of the `site:team:add` command defaults to `team_member`. (#1750)

### Added
- Added a `machine_name` field to the output of `upstream:list`. (#1747)
- Added a `organization` field to the output of `upstream:list`. (#1747)
- Added a `machine_name` field to the output of `upstream:info`. (#1747)
- Added a `organization` field to the output of `upstream:info`. (#1747)
- Added a `repository_url` field to the output of `upstream:info`. (#1747)
- Added a `org:upstream:list` command to list the upstreams of a specific organization. (#1747)
- Added an `--org` option to `upstream:list` to list the upstreams of a specific organization. (#1747)
- Added an `--all` option to list upstreams of all types in the output of `upstream:list`. (#1747)
- Added a `--framework` option to `upstream:list` to filter the list by framework. (#1747)
- Added a `--name` option to `upstream:list` to filter the list by name regex. (#1747)

### Removed
- Removed the `category` field from the output of `upsteram:info`. (#1747)

### Fixed
- The `org` option of `site:create` now works with machine names and labels as well as UUIDs. (#1747)
- If the `change_management` feature is not enabled on a site, no warning is displayed only if the `role` has been supplied and is not `team_member`. (#1750)

## 1.4.1 - 2017-07-17
### Fixed
- Corrected the help text of `import:site`/`site:import` to call the params params rather than options. (#1718)
- Pin the version of reflection-docblock to prevent syntax-checking problems with @usage tags. (#1740)

### Added
- Added a Collection::filter(callable) function. (#1725)
- Added a `frozen` column to the output of `org:site:list`. (#1726)

## 1.4.0 - 2017-06-07
### Fixed
- Removed the element option's erroneous "all" value from `backup:get`, changed its default to "files". (#1705)

### Added
- Added an experimental `site:upstream:set` command to switch a site's upstream. (#1713)

## 1.3.0 - 2017-04-20
### Added
- `env:commit` now has a `--force` option to force a commit even if no changes are found. (#1115)

## 1.2.1 - 2017-04-11
### Fixed
- Corrected the command to be used to update Terminus displayed by the `UpdateChecker`. (#1687)

## 1.2.0 - 2017-04-10
### Changed
- `Backup::getDate()` now returns a Unix datetime instead of a formatted date. (#1676)

### Added
- The `backup:info` command has been added. (#1676)
- Added expiration dates to backups in `backup:list`. (#1676)
- `Backup::getExpiry()` calculates the Unix datetime of a backup's expiry. (#1676)

### Fixed
- Updates to dependencies have been applied. (#1675)
- Fixed ambiguous text in `env:commit` and `env:diffstat` help description. (#1685)

## 1.1.2 - 2017-03-31
### Changed
- Reenabled the `self:console` command in PHP 7.1. (#1664)
### Fixed
- Corrected typo in `aliases` command which prevented the authorization hook from working on it. (#1663)
- Updated to match changes made to Config class in Robo 1.0.6. (#1670)

## 1.1.1 - 2017-03-09
### Fixed
- composer.json file now has its `bin` property set to include `bin/terminus`. (#1656)

## 1.1.0 - 2017-03-09
### Added
- Added an `--element=` option to `backup:list`. (#1563)
- Added the label column to `org:list`'s output. (#1612)
- Added the `upstream:updates:status` command to report whether any site environment is outdated or current. (#1654)

### Changed
- `self:cc` now acts to delete all files in the command cache directory. (#1569)
- `env:clone-content` and `env:deploy` now refuse to clone from uninitialized environments. (#1608)
- Encapsulation of the properties of models and collections has been tightened. Please use getter and setter methods to access them. (#1615)
- The column labeled as `name` in `org:list`'s output now contains the machine name of an organization. (#1612)
- Any command using an `organization` parameter or `org` option now accepts an organization's UUID, name, and label. (#1612)
- The first parameter of `SiteOrganizationMemberships::create($org, $role)` is now an Organization object. (#1612)

### Deprecated
- The `element` parameter on `backup:list` is deprecated. Use the `--element=` option instead. (#1563)
- The `wait()` function of `Workflows` is deprecated. Use `checkStatus()` instead. (#1584)
- The `User::getOrgMemberships()` is deprecated. Use `User::getOrganizationMemberships()` instead. (#1613)

### Fixed
- Fixed the base branch in the URL when checking for upstream updates. (#1581)
- Fixed `new-relic:info` by changing `NewRelic::serialize()` to fetch its data before attempting to return it. (#1648)
- Removed login information from the debug output. (#1642)

## 1.0.0 - 2017-01-20
### Added
- Added `--to=` option to `backup:get` to allow specifying of a local download location. (#1520)

### Fixed
- Fixed `backup:restore`. (#1529)
- Fixed `env:wake` to target domains with zone names. (#1530)
- Fixed `env` commands to not err when the site is frozen and the test or live environment is to be accessed. (#1537)

### Changed
- Clear cache no longer deletes stored machine tokens. Logout now deletes stored machine tokens. (#1542)
- Terminus now checks for new versions after every command run. (#1523)
- `site:create` now checks to see whether a site name is taken before attempting to create it. (#1536)

### Removed
- Removed framework type check from `drush` and `wp` commands. (#1521)

## 1.0.0-beta.2 - 2017-01-10
### Fixed
- Fixed fatal error by adding back the use statement for ProcessUtils in SSHBaseCommand. (#1494)
- Pinned PHP-VCR version to 1.3.1 due to issues with turning PUT into POST. (#1501)

## 1.0.0-beta.1 - 2016-12-21
### Changed
- Moved to Symfony Console
- Updated command structure. Please see [https://pantheon.io/docs/terminus/commands/compare](https://pantheon.io/docs/terminus/commands/compare) for updates.

_Terminus version v1.0 and later introduces a new command line and argument structure that is incompatible with any custom scripts that use 0.x Terminus or older plugins that you may be using.
Please consider the impact to your automation scripts and plugins before upgrading to Terminus v1.0._
