[![CI](https://github.com/portrino/cms-observer/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/portrino/cms-observer/actions/workflows/ci.yml)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000.svg?logo=symfony)](https://symfony.com/releases/8.1)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-4f5b93.svg?logo=php)](https://www.php.net/releases/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE.txt)

# CMS Observer

Monitors the CMS installations of all your sites in one place. Each site
pushes a short report with its CMS core, PHP and database versions and its
installed extensions or plugins; the Observer checks them against release,
end-of-life and vulnerability data and emails you when something becomes
insecure.

A small client in the CMS sends the report. Clients exist for TYPO3 and
WordPress so far; more CMS can follow, and you're welcome to propose one via a
pull request, see [supporting another CMS](docs/clients.md#supporting-another-cms).

| CMS | Client | Checked against |
|---|---|---|
| TYPO3 12.4 – 14.3 | [cms-observer-client-typo3](https://github.com/portrino/cms-observer-client-typo3) | get.typo3.org, TER, TYPO3 security advisories |
| WordPress 6.4+ | [cms-observer-client-wordpress](https://github.com/portrino/cms-observer-client-wordpress) | wordpress.org, Wordfence Intelligence |
| PHP, MariaDB, MySQL, PostgreSQL | (part of every report) | endoflife.date |

## ✨ Features

- [**Security status per site**](docs/data-sources.md): insecure or outdated core, PHP, database and packages, the matching advisory or CVE
- [**Email notifications**](docs/configuration.md#email): one summary mail per run for every new finding, never one mail per site
- [**Change history**](docs/clients.md#what-a-report-changes): when which core, PHP, database or package version changed, and when something became insecure
- [**Upgrade calculations**](docs/upgrade-calculations.md): effort and price of a major core upgrade from the latest report, versioned, comparable and printable
- [**Staging-safe reports**](docs/clients.md#domain-check): reports from another domain, e.g. a staging copy with the production token, are rejected

## 🔥 Installation

> [!IMPORTANT]
> Requires PHP 8.4+, MariaDB or MySQL 8, a web server with `public/` as
> document root and a cron job for the scheduled syncs. How you deploy is up to
> you; the repository doesn't prescribe a tool.

```bash
git clone --branch <version> https://github.com/portrino/cms-observer.git
cd cms-observer
bin/setup
```

`bin/setup` asks for the settings, checks them, writes `.env.local`, creates
the database, builds the assets and creates the first login. Updating is
`git checkout` of the new version and `bin/setup` again. Details, the cron
job and installing without interaction: [installation](docs/installation.md).

## 🚀 Quick start

1. Log in and add a project with the URL of the site.
2. Copy the token shown once after saving.
3. Install the client in the site and configure the Observer URL and the
   token, see the client's README.

The first report arrives with the client's next run; the release and
vulnerability data fills in over the next hours, or right away with the
[sync commands](docs/configuration.md#scheduled-syncs).

## 📚 Documentation

| Topic | What's inside |
|---|---|
| [Installation](docs/installation.md) | Requirements, setup, environment variables, cron job, updating |
| [Configuration](docs/configuration.md) | Email, scheduled syncs, users and two-factor authentication |
| [Data sources](docs/data-sources.md) | Where the release and vulnerability data comes from and how sites are rated |
| [Upgrade calculations](docs/upgrade-calculations.md) | Calculating major upgrades, versions, refreshing from the latest report |
| [Clients](docs/clients.md) | The report API, tokens, the domain check, supporting another CMS |
| [Contributing](CONTRIBUTING.md) | Local setup with DDEV, tests, code style, pull requests |

## 💎 Credits

Inspired by [t3monitoring](https://github.com/georgringer/t3monitoring) and the
SYSSY monitoring.

## ⭐ License

[GPL-2.0-or-later](LICENSE.txt)
