# RuleTicket Inspector core

## Scope

Phase 3 implements a read-only diagnostic pipeline for a persisted Ticket:

```text
Ticket -> TicketContextBuilder -> RuleTicketCandidateProvider
       -> RuleTicketInspector -> InspectionResult
```

It does not call `process()`, `processAllRules()`, or `executeActions()`. Rule
actions are loaded read-only to preserve core sequential semantics for every
inspection. The opt-in `InspectionOptions::includeActions` controls only
whether reflected actions are attached to `RuleInspection`; a separate immutable
projection always supplies the next selected rule's context. A reported match,
projected effect, or reflected action is not proof that the rule executed
historically. See [action analysis](action-analysis.md).

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
ordering without duplicating its SQL. The collection does not hydrate actions;
the sequential engine obtains them in one separate read-only batched query.

The default inspection limit is 1000 rules and can be configured to any
positive integer. `InspectionResult` reports the configured limit, known
candidate count, evaluated count, and whether the result was truncated.

## Sequential simulated context

The Inspector retains the exact candidate order returned by
`RuleTicketCollection` and creates one immutable
`SequentialRuleStep` per evaluated rule. Each step records its native order,
input context, criterion result, configured actions, projected effects, and
output context. ONADD and ONUPDATE each start from an independent persisted
snapshot; no simulated output crosses conditions.

`RuleEffectProjector` is deliberately smaller than GLPI action execution. It
projects only reviewed scalar `assign` fields, category assignment (including
the linked `itilcategories_id_code`), and exact-null deadline `delete` actions.
Actor, append, computed, lookup, regex, template, SLA/OLA, transient, plugin,
and otherwise unknown behavior is not approximated. If a matching or
indeterminate rule could alter a known criterion through an unsupported effect,
that criterion and reviewed derived dependencies become `INDETERMINATE` for
later steps. The trace stays inside
Inspector DTOs in this phase and is not exposed to Twig, HTML, or JavaScript.

## Validation boundary

Unit tests cover the GLPI-independent value objects and reducer. The
`glpi-integration` suite exercises real GLPI context reconstruction, native
candidate selection/evaluation, limits, indeterminate cases, and persistence
plus Ticket-history snapshots before/after inspection. Integration fixtures delete only IDs created
by their own test.
