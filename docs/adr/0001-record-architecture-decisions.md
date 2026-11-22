# ADR-0001: Record architecture decisions

- Status: Accepted
- Date: 2026-11-22
- Deciders: Maintainers

## Context

Some decisions about the Observer and its clients are not visible in the
code, especially features we decided not to build. Without a record,
the same ideas come up again, and nobody remembers why they were turned
down or under which conditions they would make sense.

## Decision

We record decisions that are hard to reverse, affect the Observer and
its clients together, or that a new contributor would ask about, as
Architecture Decision Records in `docs/adr/`. Each is one Markdown file
named `NNNN-short-title.md` with a four-digit sequential number and uses
the template below. Code style, routine version bumps and anything
that is cheap to reverse don't get an ADR. Earlier decisions are only
recorded when they cause confusion.

An accepted ADR is not edited. If a decision changes, a new ADR
supersedes it, and the old one only gets its status changed to
"Superseded by ADR-NNNN".

Template:

```markdown
# ADR-NNNN: Short title of the decision

- Status: Proposed | Accepted | Rejected | Deprecated | Superseded by ADR-NNNN
- Date: YYYY-MM-DD
- Deciders: Maintainers

## Context
## Decision
## Consequences
## Alternatives considered
## Links
```

## Consequences

- Positive: The reasons behind decisions, including dropped features,
  stay with the code and are public.
- Negative: Each decision takes a few minutes to write down.
- Neutral: There is no scheduled review. An ADR names the condition under
  which it should be reconsidered.

## Alternatives considered

- GitHub issues: they get closed and are hard to find later.
- A section in the existing docs: those describe how things are, not why.

## Links

- Michael Nygard, [Documenting Architecture Decisions](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions)
