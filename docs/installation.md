# Installation

## Requirements

- PHP 8.4 or newer with the extensions `ctype`, `iconv`, `pdo_mysql` and
  `sodium`, and [Composer](https://getcomposer.org)
- MariaDB or MySQL 8
- a web server with `public/` as document root
- a cron job (or another way to run a command every minute) for the
  scheduled syncs and security mails
- outgoing HTTPS to the [data sources](data-sources.md), to GitHub for the
  Sass binary on the first build, and an SMTP server (or any other
  [Symfony Mailer transport](https://symfony.com/doc/current/mailer.html#using-built-in-transports))

Node.js and npm aren't needed: the first build downloads the standalone Dart
Sass binary to `var/sass/`, and the JavaScript packages come through the
importmap. A server without access to GitHub can use an installed Dart Sass
instead, set via `binary` in `config/packages/symfonycasts_sass.yaml`, or
[build elsewhere](#building-elsewhere).

## Setup

Install a released version, never `main` (the latest version is on the
[releases page](https://github.com/portrino/cms-observer/releases)):

```bash
git clone --branch <version> https://github.com/portrino/cms-observer.git
cd cms-observer
bin/setup
```

`bin/setup` installs the dependencies and, on the first run, asks for the
settings, checks them and writes `.env.local`:

| Setting | Variable | Notes |
|---|---|---|
| Public URL of the Observer | `DEFAULT_URI` | used for the links in the mails |
| Database URL | `DATABASE_URL` | e.g. `mysql://user:pass@127.0.0.1:3306/observer`; the server version is detected and added, the database is created if it doesn't exist |
| Mailer DSN | `MAILER_DSN` | e.g. `smtp://user:pass@smtp.example.com:587` |
| Sender address | `MAILER_FROM_ADDRESS` | defaults to `no-reply@<host of the URL>` |
| Wordfence API key | `WORDFENCE_API_KEY` | optional, free key from wordfence.com → Account → Integrations; without it WordPress plugins aren't checked for vulnerabilities |
| (generated) | `APP_SECRET`, `TOTP_ENCRYPTION_KEY` | back up `TOTP_ENCRYPTION_KEY`: without it every two-factor login has to be reset. `app:upgrade` stops if `APP_SECRET` is empty: it signs the "Remember me" logins |

Then it creates the database schema, builds the assets, asks for the first
login and prints the cron job to add:

```
* * * * * /usr/bin/php /path/to/cms-observer/bin/console messenger:consume scheduler_default --time-limit=55 --memory-limit=128M
```

`.env.local` is readable by its group only; the web server's user needs to
be its owner or in its group.

### Without interaction

For scripts or provisioning, pass the settings as options:

```bash
bin/setup --no-interaction \
  --observer-url=https://observer.example.com \
  --database-url=mysql://user:pass@127.0.0.1:3306/observer \
  --mailer-dsn=smtp://user:pass@smtp.example.com:587
php bin/console app:user:create <email> <name>   # asks for the password
```

If the settings come from real environment variables (e.g. in a container)
instead of `.env.local`, `bin/setup` skips the questions and only updates;
`bin/setup --setup` forces them.

## Updating

Read the release notes of the new version first, and back up the database:
migrations can't always be undone.

```bash
git fetch --tags
git checkout <version>
bin/setup
```

With an existing `.env.local`, `bin/setup` only updates: dependencies,
database migrations, assets and cache. The report API stays compatible with
older clients, so the Observer can be updated before the clients.

## Building elsewhere

`bin/setup` builds on the server. To build on a CI or build server instead,
run there

```bash
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod php bin/console importmap:install
APP_ENV=prod php bin/console sass:build
APP_ENV=prod php bin/console asset-map:compile
```

and on the server, after copying the result, `php bin/console app:upgrade --skip-assets`.
