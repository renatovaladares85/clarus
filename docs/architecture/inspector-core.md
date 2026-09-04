# RuleTicket Inspector core

## Scope

Phase 3 implements a read-only diagnostic pipeline for a persisted Ticket:

```text
Ticket -> TicketContextBuilder -> RuleTicketCandidateProvider
       -> RuleTicketInspector -> InspectionResult
```

It does not call `process()`, `processAllRules()`, or `executeActions()`. Rule
actions are not loaded by default and are never simulated. With the opt-in
`InspectionOptions::includeActions`, only actions of evaluated rules are loaded
read-only and attached to their `RuleInspection`. A reported match or reflected
action describes the current reconstructable Ticket state and is not proof that
the rule executed historically. See [action analysis](action-analysis.md).

## Context and evaluation

`TicketContextBuilder` starts from the criteria exposed by GLPI's
`RuleTicket::getCriterias()`. Persisted Ticket fields are available, including
persisted `null`; actor relationships, category code, requester groups,
requester location, and requester profile are derived through native read APIs.
Values that require request headers, the original update change set, earlier
rule output, or other non-persisted runtime state remain explicitly
`INDETERMINATE` rather than receiving synthetic empty values.

Individual available criteria are evaluated with GLPI's native Rule engine.
The overall result uses three-valued AND/OR reduction. Rules without criteria
are `INDETERMINATE`, because GLPI's processing flow rejects them. UPDATE rules
are also overall `INDETERMINATE`: the persisted Ticket does not contain the
original set of fields that made the rule eligible during that update.

## Candidate selection and limits

`RuleTicketCandidateProvider` uses
`RuleTicketCollection::getCollectionDatas(1, 0, $condition)`. This preserves
GLPI's active, subtype, condition, entity/recursive, and ranking selection and
ordering without duplicating its SQL. Actions are not retrieved.

The default inspection limit is 1000 rules and can be configured to any
positive integer. `InspectionResult` reports the configured limit, known
candidate count, evaluated count, and whether the result was truncated.

## Validation boundary

Unit tests cover the GLPI-independent value objects and reducer. The
`glpi-integration` suite exercises real GLPI context reconstruction, native
candidate selection/evaluation, limits, indeterminate cases, and a persistence
snapshot before/after inspection. Integration fixtures delete only IDs created
by their own test.
