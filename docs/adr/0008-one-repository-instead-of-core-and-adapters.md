# ADR-0008: One repository instead of a core and CMS adapters

- Status: Accepted
- Date: 2026-11-28
- Deciders: Maintainers

## Context

The Observer supports TYPO3, WordPress and Craft CMS in one Symfony
application. It was proposed to split it like TYPO3 splits its core:
a package `cms-observer-core` and one adapter package per CMS
(`cms-observer-adapter-typo3`, `-wordpress`, `-craftcms`), so that an
agency monitoring only TYPO3 requires only the core and that adapter.

The CMS-specific code mostly lives in its own folders already
(`Entity/Typo3`, `Service/Wordpress`, `Command/Craft`, ...), but the parts
that tie it together don't allow a split:

- `CmsType` and `PackageType` are PHP enums, which a package can't extend,
  so an adapter couldn't add its CMS.
- About a dozen classes outside the CMS folders choose per CMS with a
  `match` (e.g. `ReportedPackageEvaluator`), as do templates, translations
  and forms.
- All tables share one migration chain; each adapter would need its own
  migrations and Doctrine mapping, and existing installations a way over.

## Decision

The Observer stays one application in one repository, with all supported
CMS. An installation that monitors only some of them carries the others'
tables and syncs, which stay empty or cheap without such projects.

We reconsider a split when third parties want to write adapters for a CMS
we don't support ourselves. If only hiding unused CMS is wanted, a setting
for the monitored CMS (hiding them in the interface and skipping their
syncs) comes first; it gives most of a split's benefit for a fraction of
its cost.

## Consequences

- Positive: one repository, one version, one migration chain and one
  release; a change across CMS (e.g. a new report field) is one commit.
- Positive: no extension points to design and keep stable for adapters
  nobody else writes.
- Negative: an installation can't leave out a CMS; it installs the code,
  tables and scheduled syncs of all of them.
- Neutral: new CMS-specific code keeps going into its CMS folder, so a
  later split remains a move rather than a rewrite. Where a `match` per CMS
  is touched anyway, an interface with tagged services is the preferred
  replacement.

## Alternatives considered

- Core and adapter packages (the proposal): rejected for now, see the
  context; four repositories with coordinated versions for a benefit only
  installations with a single CMS would see.
- A setting for the monitored CMS: not built until someone asks for it.
- Symfony bundles per CMS inside this repository: the same extension points
  as packages without their benefit, as everything still ships together.

## Links

- [AGENTS.md](../../AGENTS.md), "Clients": the clients stay separate
  packages, as they run inside the CMS
