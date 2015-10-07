Contribute
==========

Creating Issues
---------------

Run `terminus cli version` to confirm you are [running the latest version](https://github.com/pantheon-systems/cli/wiki/Installation) before opening a new issue.

Setting Up
----------

1. Clone this Git repository on your local machine.
2. Install [Composer](https://getcomposer.org/) if you don't already have it.
2. Run `composer install` to fetch all the dependencies.
3. Run `./bin/terminus --info` to test that everything was installed properly.
4. Download PHPUnit: `curl -L https://phar.phpunit.de/phpunit.phar > phpunit.phar`
5. Download Behat: `curl -L http://behat.org/downloads/behat.phar > behat.phar`

Submitting Patches
------------------

Whether you want to fix a bug or implement a new feature, the process is pretty much the same:

0. [Search existing issues](https://github.com/pantheon-systems/cli/issues); if you can't find anything related to what you want to work on, open a new issue so that you can get some initial feedback.
1. [Fork](https://github.com/pantheon-systems/cli/fork) the repository.
2. Push the code changes from your local clone to your fork.
3. Open a pull request.

It doesn't matter if the code isn't perfect. The idea is to get it reviewed early and iterate on it.

If you're adding a new feature, please add one or more functional tests for it in the `features/` directory. See below.

Lastly, please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Running and Writing Tests
-------------------------

There are two types of automated tests:

* unit tests, implemented using [PHPUnit](http://phpunit.de/)
* functional tests, implemented using [Behat](http://behat.org)

### Unit Tests

The unit test files are in the `tests/` directory.

To run the unit tests, execute:

    php phpunit.phar

### Functional Tests

The functional test files are in the `features/` directory.

Before running the functional tests, you'll need a MySQL user called `wp_cli_test` with the
password `password1` that has full privileges on the MySQL database `wp_cli_test`.
Running the following as root in MySQL should do the trick:

    GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1";

To run the entire test suite:

    php behat.phar --expand

Or to test a single feature:

    php behat.phar features/core.feature

More information can be found by running `php behat.phar --help`.

Versioning 
--------------

### Versions 

In keeping with the standards of semantic versioning, backward-incompatible fixes are targeted to "Major" versions. "Minor" versions are reserved for significant feature/bug releases needed between major versions. "Patch" releases are reserved only for critical security issues and other bugs critical to stabilizing the release. 

After a new major version is released, previous major versions are actively supported for 1 year. 

#### What qualifies as a backward incompatible change?

Our initial commitment is to command compatibility and parameter compatibility. However, since on the command line STDOUT and STDERR are essentially APIs, we will make a best effort to keep machine-readable output compatibility. If your code interfaces with Terminus via --json or --bash formats, we will try our best to ensure these are stable and compatible between minor release. However, changes to the STDOUT, like success and fail messages, are not considered incompatible. 

### Version Branches 

If you are using Terminus in a production environment, you should be deploying the executable for the latest release. ( github.com/pantheon-systems/cli/releases )

Ongoing development on the next planned release will be on the master branch and should not be considered stable as changes will be taking place on a daily basis. 

We will maintain a separate branch for all minor point releases going forward, e.g. 1.0.x, 0.5.x. Submit any critical patches to those branches. If the fix is applicable to master, make a a separate pull request.

#### What does this mean for users?

0.5.0 will include only changes that are backward incompatible. After it's released, we will create a 0.5.x branch that will be used for any critical bugs or patches that need to be addressed. All other bugs/features/issues will be addressed in the next major point release 1.0.0. The master branch will effectively become the 1.0.x branch. There is currently no plan for a version 0.6.x. 

----------

Thanks! Hacking on Terminus should be fun. If you find any of this hard to figure
out, let us know so we can improve our process or documentation!
