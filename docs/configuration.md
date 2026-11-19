# Configuration

All settings are environment variables, see
[installation](installation.md#setup).

## Email

Every user of the dashboard gets the security mails. One sync run or report
collects all new findings, e.g. a new TYPO3 security release affecting twenty
sites, into a single mail. A finding is only mailed once: when a core, PHP or
database version or a package becomes insecure, not on every later check.

The mails link to the projects, so `DEFAULT_URI` must be the Observer's
public URL.

## Scheduled syncs

The worker started by the [cron job](installation.md#cron-job) runs these
syncs. Each can also be run by hand, e.g. right after the installation:

| Command | Schedule | Fetches |
|---|---|---|
| `app:typo3:sync-core-releases` | every 6 hours | TYPO3 releases and their security/support status |
| `app:typo3:sync-extension-advisories` | hourly | TYPO3 extension security advisories |
| `app:typo3:sync-extension-versions` | every 15 minutes | TER versions of the reported extensions, 20 per run |
| `app:wordpress:sync-core-releases` | every 6 hours | WordPress releases and which are insecure |
| `app:wordpress:sync-plugins` | every 15 minutes | wordpress.org versions of the reported plugins, 40 per run |
| `app:wordpress:sync-plugin-vulnerabilities` | daily | the Wordfence vulnerability feed (needs `WORDFENCE_API_KEY`) |
| `app:sync-endoflife-releases` | daily | PHP, MariaDB, MySQL and PostgreSQL support cycles |

Wordfence rate-limits strictly, so don't run its sync repeatedly by hand.

## Users and two-factor authentication

```bash
php bin/console app:user:create <email> <name> <password>   # a new login
php bin/console app:user:reset-2fa <email>                  # e.g. after a lost phone
```

Users set up two-factor authentication (TOTP, with backup codes) in their
profile. The TOTP secrets are encrypted with `TOTP_ENCRYPTION_KEY`; without a
valid key the profile doesn't offer the setup.

## Projects

Projects (sites) are created in the dashboard or on the command line:

```bash
php bin/console app:site:create "<name>" <typo3|wordpress> "<site-url>"
```

Both print the site's token once; the client needs it. The token is stored
hashed, so a lost one can't be shown again. Generate a new one in the
project's edit form or with:

```bash
php bin/console app:site:list
php bin/console app:site:regenerate-token <id>
```

The old token stops working immediately. The site URL must be
the URL the site really uses, see the [domain check](clients.md#domain-check).
