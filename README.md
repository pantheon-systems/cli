# terminus : Pantheon's Command-Line Interface

## Status

[![Commit Build](https://github.com/pantheon-systems/terminus/actions/workflows/ci.yml/badge.svg?branch=v3.0)](https://github.com/pantheon-systems/terminus/actions/workflows/ci.yml)
[![Actively Maintained](https://img.shields.io/badge/Pantheon-Actively_Maintained-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#actively-maintained-support)

## About

Terminus is Pantheon's Command Line Interface (CLI), providing at least equivalent functionality to the Pantheon's
browser-based Dashboard and easier scripting.

If you would like to contribute, pull requests are welcome!

## The Manual

Our documentation is kept in the Terminus Manual, located here: https://pantheon.io/docs/terminus

## Requirements

| Operating System       | Version    |
|------------------------|------------|
| MacOS                  | 10.14+     |
| Ubuntu                 | Latest LTS |
| Windows + WSL + Ubuntu | TBD        |

### Package Manager

- [apt](https://ubuntu.com/server/docs/package-management) for Ubuntu/WinWSL-Ubuntu

- [Homebrew](https://brew.sh) for mac

#### Required Packages

These packages are required to take full advantage of Terminus.

- [Composer 2](https://getcomposer.org) (Needed for the plugin manager component)

- [PHP](https://www.php.net) (v7.4+)

- [Git](https://help.github.com/articles/set-up-git/) (May be needed for the plugin manager component)

- OpenSSH 7.8 or later

#### Recommended Packages

- [Drush](http://docs.drush.org/en/master/install/) (Useful to run incompatible-with-Terminus Drush commands)

- [WP-CLI](http://wp-cli.org/) (Useful to run incompatible-with-Terminus WP-CLI commands)


## Installation

### Mac OS:

Terminus is published as a package under pantheon-systems/external. To install it, you should run:

```
brew install pantheon-systems/external/terminus
```

### Ubuntu / WinWSL+Ubuntu:

```
# Setup repository
curl -s https://packagecloud.io/install/repositories/pantheon/public/script.deb.sh | bash
# Install package
apt install -y terminus
```

### Other installation methods

Refer to the [Terminus manual](https://pantheon.io/docs/terminus/install#install-terminus) for other installation methods.
