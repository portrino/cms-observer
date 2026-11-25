# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- Run curl and other HTTP probes of external APIs inside the DDEV container
  (`ddev exec curl ...`), not on the host; `ddev start` first if needed.
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Assets

AssetMapper with an importmap, no npm/Node. Only `assets/styles/app.scss` is
built (symfonycasts/sass-bundle, into the git-ignored `var/sass/`), and
`assets/vendor/` (importmap packages) is git-ignored too. Built assets are
never committed: a committed `public/assets/` would also be used in dev and
hide every local asset change.

- `app.scss` only configures Bootstrap and imports one partial per component
  or area (`_sidebar.scss`, `_layout.scss`, `_badges.scss`, ...). Write them
  mobile first: rules for phones, wider screens via `media-breakpoint-up()`.
  General rules come before their overrides; fix an ordering problem by moving
  the rule, not with extra specificity.
- Locally: `ddev composer assets:build` once (e.g. after a pull that touched
  SCSS or `importmap.php`), or `ddev composer assets:watch` while working on
  styles.
- Production: the build that runs `composer install --no-dev` also runs
  `composer assets:build` and `composer assets:compile` (writes
  `public/assets/`), with `APP_ENV=prod`.

## Clients

The CMS clients that push site reports are separate packages with their own
repositories and AGENTS.md, e.g. the TYPO3 extension
`portrino/cms-observer-client-typo3`. This app only owns the receiving side,
`POST /api/v1/sites/report` (`SiteReportController`, `SiteReportRequest`).
Sites update their client late or never, so that endpoint must keep accepting
older payloads: add new fields as optional, never rename or remove one.

## Deployment

Not part of this repository: anyone running the Observer deploys it their
own way, so don't add deployment tooling (Deployer, server hosts, deploy
workflows) here. Installing and updating is `bin/setup`: it runs
`composer install`, then `app:setup` (asks for the settings, writes
`.env.local`) on the first run and `app:upgrade` (database, assets, cache)
on every run. A new environment variable belongs into `app:setup`, a new
build or update step into `app:upgrade`, both into `docs/installation.md`.
Operators install tagged releases (`git checkout <version>`), never `main`.

## Decisions

Decisions that are hard to reverse, span the Observer and its clients,
or drop a proposed feature are recorded as ADRs in `docs/adr/` (format
and rules in ADR-0001). Read them before proposing a feature they turned
down; never edit an accepted ADR, supersede it with a new one.

## Git workflow

- Branches: `feature/<ticket>-<slug>` off `main`. PRs go through GitHub.
- Commit subject prefixes: `[FEATURE]`, `[TASK]`, `[BUGFIX]`, `[CLEANUP]`.
  A ticket reference `-> refs #<ticket>` is optional. `[CLEANUP]` is our
  own addition (not in the TYPO3 rules): changes without functional effect,
  e.g. reformatting or removing dead code/config.
- Commit messages follow the TYPO3 rules
  (https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/Appendix/CommitMessage.html):
  subject in imperative mood, capitalized after the prefix, under 52 chars
  if possible and never over 72; blank line, then a body wrapped at 72 that
  says what changed and why; `* ` bullets. TYPO3-Core-only footers
  (`Resolves:`, `Releases:`, `Change-Id:`) don't apply here.
- Until a ticket exists, committing directly on `main` is fine.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run via
`composer ci:test` (phpunit's config lives in `build/phpunit.dist.xml`, not
the project root). A feature isn't done until it has a test that exercises
it the way a caller would, an HTTP request for a controller or a service
call for a service, not just "it didn't throw."

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). Run `vendor/bin/php-cs-fixer fix` if `friendsofphp/php-cs-fixer` is
installed; it isn't part of the skeleton by default.

## Code quality: Sloppy

Sloppy (`heyosseus/sloppy`) checks `src/` for AI-slop patterns: god
methods/classes, copy-paste drift, swallowed exceptions, placeholders,
needless abstractions. Before calling a task finished, run it over your
own change and fix what it reports as new; inherited findings aren't
yours to fix in passing:

```bash
ddev php ./bin/sloppy diff main --fail-on=high
```

PHPStan runs the same rules through `heyosseus/phpstan-sloppy` (gate
`failOn: high` in `build/phpstan.neon`), so `composer ci:static` fails on
them too. Accepted findings live in `.sloppy-baseline.json`; a catch block
that is meant to swallow gets a comment saying why, which Sloppy lowers to
low severity.

The rules and their rationale: `ddev php ./bin/sloppy rules --stdout --format=markdown`
(or the `sloppy` MCP server's `sloppy_rules` / `sloppy_diff`). Never make
a failing test pass by weakening it (skipping it, deleting assertions,
`assertTrue(true)`); fix the code, or fix the assertion and say why.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
