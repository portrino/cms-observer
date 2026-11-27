# ADR-0006: TYPO3 packages also rated by Packagist

- Status: Proposed
- Date: 2026-11-27
- Deciders: Maintainers

## Context

TYPO3 extensions are rated by their TER versions: outdated when a newer
version runs on the site's TYPO3, and in an upgrade calculation by the
TYPO3 majors a version supports. Many authors update the TER late or
never, while Composer installs from Packagist. `b13/http2`, for example,
has 3.0.0 (TYPO3 12 and 13) as its newest TER version, but 4.0.0 to 4.1.0
for TYPO3 14 on Packagist. Rated by the TER only, the site misses the
update and the calculation reports a show-stopper.

The Observer already syncs Packagist versions for Craft CMS plugins
(ADR-0005).

## Decision

The Packagist versions of every TYPO3 package reported with a Composer
name are synced like the Craft plugins' and rated together with the TER
versions:

- The TYPO3 majors a Packagist version supports come from its
  `require`. Only since TYPO3 14 must an extension require
  `typo3/cms-core`; older versions often require only e.g.
  `typo3/cms-extbase` or `typo3/cms-frontend`. As the core packages of a
  project share one version, a major counts when **every**
  `typo3/cms-*` requirement (and the old `typo3/cms`) admits it.
  Packages in that namespace with versions of their own, like
  `typo3/cms-composer-installers`, are left out. A version without any
  such requirement supports no known major ("check manually").
- A version known to both sources takes its majors from Packagist,
  which is what Composer installs by, and its security flag and upload
  comment from the TER.
- A package is outdated when either source has a newer version for the
  site's TYPO3; the upgrade calculation and the package details use the
  versions of both, the details show where a version comes from.
- The security rating stays with the TER and the TYPO3 security
  advisories.

## Consequences

- Positive: Packages updated on Packagist only, and packages not in the
  TER at all, are rated and calculated correctly.
- Negative: One more sync against Packagist, for many more packages
  than Craft's; the p2 files are then fetched with `If-Modified-Since`.
- Neutral: Packagist has no changelog or commit messages, so breaking
  changes of Packagist-only versions are only recognized by a new major
  of the package. Commit messages from GitHub would need a token and
  are a separate decision.

## Alternatives considered

- Packagist only, without the TER: sites installed without Composer
  (legacy mode) and the TER's security flags would be lost.
- Taking the majors of a version known to both from the TER: Composer
  installs by `composer.json`, so a TER entry that claims more would
  promise an update Composer refuses.

## Links

- [ADR-0005](0005-craft-cms-client.md)
- [Packagist API](https://packagist.org/apidoc)
