# Contributing

Thank you for considering contributing to this project! Every contribution is welcome and helps improve the quality of
the project. To ensure a smooth process and maintain high code quality, please follow the steps below.

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/)

## Preparation

```bash
# Clone repository
git clone https://github.com/portrino/cms-observer.git
cd cms-observer

# Start the project with DDEV
ddev start

# Install dependencies and build the CSS
ddev composer install
ddev composer assets:build
```

Create a `.env.local` (not committed) with the values for DDEV:

```dotenv
DATABASE_URL="mysql://db:db@db:3306/db?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://localhost:1025
APP_SECRET=any-random-string
# optional, without it the Wordfence sync is skipped
WORDFENCE_API_KEY=
```

These belong into `.env.local`, which every environment except test reads. The scheduler cron job in DDEV
(`.ddev/crontab`) runs with `--env=dev`, so it always uses the current code. For the tests create a `.env.test.local` with the same `DATABASE_URL` (the test environment doesn't read
`.env.local`, it appends `_test` to the database name).

```bash
# Create the database schema and a login
ddev console doctrine:migrations:migrate -n
ddev console app:user:create <email> <name> <password>

# Open the dashboard (https://cms-observer.ddev.site/) and the sent mails (Mailpit)
ddev launch
ddev launch -m
```

The release and vulnerability data is synced by the scheduler over time, run the
[sync commands](docs/configuration.md#scheduled-syncs) (`ddev console app:…`) to fetch it right away. Reports come in
through the clients, see [clients](docs/clients.md).

### After a pull or switching branches

Repeat the steps that the changes need. In doubt run all of them, they do nothing when nothing changed.

```bash
ddev composer install                                    # composer.lock changed
ddev composer assets:build                               # SCSS or importmap.php changed
ddev console doctrine:migrations:migrate -n              # new migrations
ddev console doctrine:migrations:migrate -n --env=test
```

## Run composer scripts

```bash
# Run all static code analyzers (as in the CI workflow)
ddev composer ci

# Specific static code analyzers
ddev composer ci:json:lint
ddev composer ci:php:cs-fixer
ddev composer ci:php:lint
ddev composer ci:php:rector
ddev composer ci:php:stan
ddev composer ci:yaml:lint

# Fix the code style
ddev composer fix:php:cs

# Rebuild the CSS on every change
ddev composer assets:watch
```

## Run tests

```bash
# Create the test database once
ddev console doctrine:database:create --env=test
ddev console doctrine:migrations:migrate --env=test -n

# Run all tests
ddev composer ci:test

# Run unit or functional tests only
ddev composer ci:test:unit
ddev composer ci:test:functional

# Run the end-to-end tests in tests/E2E
ddev npm install --prefix tests/E2E
ddev e2e-reset
ddev playwright test
```

The end-to-end tests run against https://e2e.cms-observer.ddev.site, which
uses a database of its own (`e2e`, switched in `public/index.php` inside DDEV
only), not the dev one. `ddev e2e-reset` recreates it with the demo projects
of `app:demo:seed --offline`, their fixed release data and the E2E logins;
run it again whenever a run left something behind or the snapshot changed.

## Translations

Texts of the interface and the mails are translation keys, e.g.
`project.add`, in `translations/messages+intl-icu.en.xlf` and `.de.xlf`
(ICU message format for parameters and plurals). Validator messages are in
`translations/validators.*.xlf`, keyed by their English text.

- Add every new key to both files; a test fails if a language misses one.
- `GermanPagesTest` opens the main pages in German and fails on a key that
  shows up untranslated; add a new page there.
- Enums shown to users implement `TranslatableInterface`; their `label()`
  stays English for the console and logs.
- Something saved, e.g. a history entry, keeps its translation key and
  parameters, so it is shown in the reader's language later.
- German texts avoid addressing the reader ("Als neue Version speichern");
  common technical terms like Extension, Plugin or ELTS stay English.

## Submit a pull request

After completing your work, **open a pull request** and provide a description of your changes. Ideally, your PR should
reference an issue that explains the problem you are addressing.

The code quality tools and the tests run automatically on every pull request. For more details, see the
relevant [workflows][1]. [AGENTS.md](AGENTS.md) describes the project's conventions, also for AI coding agents.

[1]: .github/workflows
