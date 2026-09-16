# RuleTicket Inspector core

## Scope

The inspector implements a read-only diagnostic pipeline for a persisted Ticket:

```text
Ticket -> TicketContextBuilder -> current snapshot RuleTicket evaluation
       -> TicketTimelineReader -> ExecutionWindowReconstructor
       -> RuleTicketReplayEngine -> InspectionResult
```

It does not call `process()`, `processAllRules()`, or `executeActions()`. Rule
actions are loaded read-only for replay. The opt-in
`InspectionOptions::includeActions` controls only whether reflected actions are
attached to `RuleInspection`; replay projection never changes the current
snapshot evaluation. A reported match, projected effect, or reflected action is
not proof that the rule executed historically. See [action analysis](action-analysis.md).

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
are `INDETERMINATE`, because GLPI's processing flow rejects them. The current
snapshot result can be calculated for an UPDATE rule, but is explicitly not a
claim about the original update input or an historical ONUPDATE execution.

## Candidate selection and limits

`RuleTicketCandidateProvider` uses
`RuleTicketCollection::getCollectionDatas(1, 0, $condition)`. This preserves
GLPI's active, subtype, condition, entity/recursive, and ranking selection and
ordering without duplicating its SQL. The collection does not hydrate actions;
the sequential engine obtains them in one separate read-only batched query.

The default inspection limit is 1000 rules and can be configured to any
positive integer. `InspectionResult` reports the configured limit, known
candidate count, evaluated count, and whether the result was truncated.

## Historical replay context

The primary `RuleInspection` result evaluates every rule against the same
current persisted context. A preceding projected action can therefore never
turn a known current Ticket value into `INDETERMINATE`.

`TicketTimelineReader` and `ExecutionWindowReconstructor` create a separate
replay input from durable GLPI history. Current values are deliberately removed
first; only an unambiguous retained `before` value restores a field. The
`RuleTicketReplayEngine` then retains the exact candidate order returned by
`RuleTicketCollection` and creates one immutable `SequentialRuleStep` per
replayed rule. ONADD and ONUPDATE use independent windows. See
[the replay architecture](rule-ticket-replay.md) for evidence and boundary
details.

`RuleEffectProjector` is deliberately smaller than GLPI action execution. It
projects reviewed scalar `assign` fields, technician-group scalar assignment,
category assignment (including the linked `itilcategories_id_code`), status
and SLA/OLA companion effects, and exact-null deadline `delete` actions.
Append, computed, lookup, regex, template, transient, plugin, and otherwise
unknown behavior is not approximated. If a matching or
indeterminate rule could alter a known criterion through an unsupported effect,
that criterion and reviewed derived dependencies become `INDETERMINATE` for
later steps. The trace stays inside
Inspector DTOs in this phase and is not exposed to Twig, HTML, or JavaScript.

## Overwrite diagnostics

`SequentialOverwriteAnalyzer` consumes only the immutable projected effects in
the native processing order. It is a separate diagnostic dimension: primary
rule results remain `MATCH`, `NO_MATCH`, and `INDETERMINATE`.

A confirmed overwrite requires two different deterministic projected values for
the same field, with the later rule evaluated after the earlier producer and no
relevant uncertain effect between them. A no-match rule, equal values, and
additive or compositional actions do not create an overwrite classification.
An indeterminate or unsupported replacement effect instead produces a possible
overwrite and breaks the deterministic producer chain. The domain record keeps
previous, intermediate, and final simulated values; the presenter exposes no
such values without a separate authorization decision.

Confirmed in the replay diagnostic does not prove historical execution of these
rules. It means only that the retained evidence and safe replay transition are
deterministic for that reconstructed window.

## Validation boundary

Unit tests cover the GLPI-independent value objects and reducer. The
`glpi-integration` suite exercises real GLPI context reconstruction, native
candidate selection/evaluation, limits, indeterminate cases, and persistence
plus Ticket-history snapshots before/after inspection. Integration fixtures delete only IDs created
by their own test.
