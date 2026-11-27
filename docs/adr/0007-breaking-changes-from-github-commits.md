# ADR-0007: Breaking changes from GitHub commits

- Status: Proposed
- Date: 2026-11-27
- Deciders: Maintainers

## Context

An upgrade calculation presets the notes of a TYPO3 package row: yellow
when the versions to update across mention breaking changes. That comes
from the TER upload comments. Versions only on Packagist (ADR-0006) have
no comment, and Packagist has no changelog or commit messages, so only a
new major of the package made a row yellow. TYPO3 extensions mark
breaking commits with "!!!", e.g. `!!![TASK] change requirements` in
`b13/http2` between 3.0.0 and 4.0.0; their repositories are mostly
public on GitHub.

## Decision

The commits between two versions are read from GitHub, optionally:

- Packagist names each version's repository and commit
  (`source.url`, `source.reference`). Only repositories on github.com
  are read, other hosts stay unchecked.
- A sync (`app:typo3:sync-breaking-commits`, hourly, a batch per run)
  compares each Packagist version of a reported TYPO3 package with its
  previous version, for the versions newer than the oldest installed
  one, and stores the commits whose subject marks a breaking change:
  "!!!" (TYPO3's convention, also "[!!!]"), "BREAKING CHANGE" and
  "feat!:" (Conventional Commits). The word "breaking" alone doesn't
  count in a commit, it also stands in "non-breaking". Commits between
  two versions never change, so each version is checked once; one GitHub
  can't compare stays unknown and isn't asked for again.
- It needs `GITHUB_API_TOKEN`: a token without any permissions
  (public repositories, read-only), as GitHub allows 60 requests an
  hour without one. Without a token the sync is skipped.
- The note presets list these commits next to the TER comments. A note
  only says "no breaking changes" when every version crossed has a TER
  comment or checked commits; otherwise it says that not every
  version's changelog is known.
- TYPO3 only, as Craft CMS has no upgrade calculations yet.

## Consequences

- Positive: Breaking changes of versions the TER lacks are found, not
  only guessed from a new major.
- Negative: GitHub becomes an optional external dependency with a token
  that expires and has a rate limit; a run stopped by the limit goes on
  in the next one.
- Neutral: The previous version is taken by number, so commits
  backported to a maintenance branch may count for the wrong line; only
  the first 250 commits between two versions are read.

## Alternatives considered

- Reading the commits when a calculation is drafted: dozens of requests
  per calculation, slow and against the rate limit.
- GitHub releases: many TYPO3 extensions don't write release notes.
- A GitHub App instead of a token: more setup for reading public data.

## Links

- [ADR-0006](0006-typo3-packages-on-packagist.md)
- [GitHub REST API: Compare two commits](https://docs.github.com/en/rest/commits/commits#compare-two-commits)
