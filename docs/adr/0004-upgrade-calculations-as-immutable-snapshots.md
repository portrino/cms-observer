# ADR-0004: Upgrade calculations as immutable snapshots

- Status: Accepted
- Date: 2026-11-22
- Deciders: Maintainers

## Context

Major-upgrade calculations (packages to update, effort, price) pass
several hands, e.g. developer, senior developer and project manager,
sometimes weeks apart. Each step must stay traceable: who changed what
and when, and which state was sent to the customer. The rows come from
the latest report of a project, but a calculation must not change when
the next report arrives.

Calculations are mainly used for TYPO3 so far, but should work for every
CMS the Observer supports.

## Decision

A calculation belongs to one project and one target core major. Every
save creates a new, immutable version with a timestamp, a required
author and a change note. The hourly rate, the project management share
and all rows are part of that version. A version can be saved as
final; later versions are still possible.

The rows of a version are stored as one JSON snapshot, not as table
rows of their own. A version is always read and written as a whole and
never queried by its rows.

The calculation itself knows no CMS. Only the prefilling of package
rows from a report is CMS-specific, done by one resolver per CMS type.

## Consequences

- Positive: The history is complete by design, nothing can be edited
  after the fact. One table per level, no copying of row entities per
  version.
- Negative: Rows can't be queried with SQL, e.g. "all calculations
  that contain package X".
- Neutral: Stored snapshots are permanent. Like the report API, the row
  format may only gain fields with defaults, never rename or remove
  one, and unknown values fall back to a safe default when read.

## Alternatives considered

- Editable calculation with an audit log: the history would have to be
  rebuilt from changes instead of being stored as is.
- Rows as entities, copied per version: more tables and code for data
  that is never queried on its own.

## Links

- [ADR-0001](0001-record-architecture-decisions.md)
