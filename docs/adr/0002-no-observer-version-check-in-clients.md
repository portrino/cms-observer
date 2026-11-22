# ADR-0002: No Observer version check in the clients

- Status: Accepted
- Date: 2026-11-22
- Deciders: Maintainers

## Context

A client release can send data that only a newer Observer understands,
e.g. `packages` (since Observer 0.7.0). An older Observer accepts such a
report with `202` and ignores the unknown fields, so the data is simply
missing until the Observer is updated. Nothing breaks, but the client
can't tell.

The idea was to return the Observer version in the `202` response, so
each client compares it with a minimum version and shows a warning in
its backend (TYPO3: a status report, WordPress: the settings page).

## Decision

We don't add a version check. A client release that needs a newer
Observer states the minimum version in its changelog, and operators
update the Observer before the clients (see
[Clients](../clients.md#compatibility)). Observer and clients keep
independent version numbers.

## Consequences

- Positive: No extra code in the Observer and both clients.
- Negative: A client sending to an outdated Observer loses the new data
  silently until the Observer is updated.
- Neutral: So far there are few operators, and they control the update
  order themselves. Reconsider when Observers are run by parties that
  don't update with the clients. The check can be added later without a
  breaking change: a response without the version means "too old".

## Alternatives considered

- Observer version in the `202` response, checked by the clients: the
  cleanest solution, but code in three repositories for a rare case.
- A shared major version for Observer and clients: forces a new major
  for every client feature that needs a newer Observer, even without a
  break.

## Links

- [Clients: Report API](../clients.md#report-api)
