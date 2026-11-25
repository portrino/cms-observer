# ADR-0005: Craft CMS as the third client

- Status: Proposed
- Date: 2026-11-25
- Deciders: Maintainers

## Context

Craft CMS sites should be watched the same way as TYPO3 and WordPress
sites.

Craft is a Composer application: the core (`craftcms/cms`) and every
plugin are Composer packages, plugins have the type `craft-plugin`.
Unlike TYPO3 (TER, get.typo3.org) and WordPress (wordpress.org,
Wordfence) there is no Craft-specific public source for versions and
vulnerabilities that the Observer could use. The Craft API
(`api.craftcms.com`) answers the sites themselves and is not documented
for third parties.

## Decision

Craft CMS becomes a new `CmsType` with its own client and an Observer
side, as described in `docs/clients.md` under "Supporting another CMS".

The client is a Craft plugin in its own repository
(`cms-observer-client-craftcms`). It pushes the existing report
payload, unchanged, from a console command run by cron, like the other
clients:

- `coreVersion`, `phpVersion`, `dbVersion`, `dbPlatform` from Craft's
  own APIs (`Craft::$app->getVersion()`, `App::phpVersion()`, the
  database schema's server version).
- `packages` from `Plugins::getAllPluginInfo()`: the handle as `name`,
  the plugin name as `title`, `version`, `packageName` as
  `composerName`, `isEnabled` as `active`, type `plugin` (the Craft
  default). License keys and license status are never sent.
- `domains` from the base URLs of the Craft sites,
  `applicationContext` from the Craft environment.

The Observer side rates Craft sites from public sources only:

| What | Source |
|---|---|
| Core and plugin versions | Packagist Composer v2 metadata (`repo.packagist.org/p2/<package>.json`), only for packages that sites report; pre-releases ignored |
| Vulnerabilities of core and plugins | Packagist Security Advisories API, matched by Composer constraint (`affectedVersions`) |
| Support cycles of a major version | endoflife.date, product `craft-cms`, in the existing end-of-life sync |

Core and plugins are rated like the other CMS: insecure when the major
version is past end of life or an advisory affects the version,
outdated when a newer stable version exists. Plugins that aren't on
Packagist stay "unknown".

Requests to Packagist follow its rules: a User-Agent with a contact
address that every operator sets in `app:setup`, at most a few
requests in parallel, no sync at the full hour.

## Consequences

- Positive: No change to the report API, older Observers simply don't
  know the CMS type yet. Packagist covers Craft's core and the common
  plugins with advisories from the GitHub Advisory Database and
  FriendsOfPHP.
- Negative: Packagist becomes a new external dependency with its own
  sync and rate rules. Packagist doesn't mark a release as "critical";
  the rating depends on advisories being published.
- Neutral: The Packagist syncs are Craft-specific for now. Whether they
  can serve other Composer-based systems is a separate decision.

## Alternatives considered

- Using an existing Craft monitoring tool: it is commercially licensed,
  needs its own server and pulls data from the sites, while the
  Observer never connects to a site.
- Sending Craft's own update information (`Updates::getUpdates()`,
  with `hasCritical` and `abandoned`) from the client: the rating would
  come from the site instead of the Observer and differ from TYPO3 and
  WordPress. It can be added later as an optional field.
- The undocumented Craft API (`api.craftcms.com`): not meant for third
  parties, Packagist has the same versions.

## Links

- [ADR-0001](0001-record-architecture-decisions.md)
- [Supporting another CMS](../clients.md#supporting-another-cms)
- [Packagist API](https://packagist.org/apidoc)
- [endoflife.date: Craft CMS](https://endoflife.date/craft-cms)
- [Craft CMS: Supported Versions](https://craftcms.com/knowledge-base/supported-versions)
