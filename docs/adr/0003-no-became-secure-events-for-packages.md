# ADR-0003: No "became secure" events for packages

- Status: Accepted
- Date: 2026-11-22
- Deciders: Maintainers

## Context

The project history has "Became insecure" and "Became secure" events for
core, PHP, database and application context. Packages only get "Became
insecure". Their status is reset on every update and combines several
sources (TER, advisories, Wordfence), so a reliable "became secure"
event would need a larger rework of the package status handling.

## Decision

We don't add "became secure" events for packages. The "Became insecure"
event already names the version with the fix, and the following
"Package updated", "Package removed" or "Package deactivated" event
shows when the problem was resolved.

## Consequences

- Positive: No rework of the package status handling.
- Negative: If a package turns secure without a change on the site,
  e.g. because an advisory is withdrawn or corrected, the history shows
  no entry for it. The project page still shows the current status.
- Neutral: Reconsider if such cases turn out to be common or a report
  needs exact "secure since" times per package.

## Alternatives considered

- Track the security status per package and log every transition: exact,
  but a larger change across all package sources.

## Links

- [ADR-0002](0002-no-observer-version-check-in-clients.md), the same
  trade-off of effort against benefit.
