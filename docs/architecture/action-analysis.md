# Read-only RuleTicket action analysis

## Scope

Phase 4 optionally enriches each `RuleInspection` with the configured actions
of that rule and compares supported effects with the current reconstructable
Ticket snapshot. `InspectionOptions::includeActions` defaults to `false`, so
action reflection presentation remains opt-in. The sequential engine still
loads configured actions read-only because it must preserve native rule-chain
semantics independently from that presentation choice.

The analyzer never calls `Rule::process()`,
`RuleCollection::processAllRules()`, or `RuleTicket::executeActions()`. It does
not persist snapshots or change Tickets, rules, actions, actors, deadlines, or
other relations.

## Support and evaluation

Support and evaluation are independent dimensions:

- `SUPPORTED`: the native effect has a defensive snapshot comparison;
- `INDETERMINATE_BY_DESIGN`: the native behavior is known but its original
  runtime input or transformed result cannot be reconstructed;
- `UNSUPPORTED`: the action is outside the initial allowlist or comes from an
  extension.

A supported action evaluates to `REFLECTED`, `NOT_REFLECTED`, or
`INDETERMINATE`. The other support states always evaluate to `INDETERMINATE`
with a stable reason code. Unknown fields never fall back to generic equality.

`REFLECTED` means only that the configured effect is present now.
`NOT_REFLECTED` means only that a complete current value contradicts the
supported effect. Neither state proves whether the rule executed historically.
Reflection always compares the persisted snapshot, never a simulated context.

## Initial native action support

Integer equality is supported for `assign` on category, type, urgency, impact,
priority, status, location, request type, validation state/percentage, and
SLA/OLA IDs. SLA/OLA derived dates remain historically indeterminate.

Actor `assign` and `append` use ID membership for requester, assigned, and
observer users/groups and assigned suppliers. Additional current actors do not
invalidate the expected membership. Native deadline `delete` actions require
the persisted target to be exactly `null`; `0` and an empty string are not
treated as null.

Dynamic source, regex, calculated priority, runtime lookup, validation,
template, and transient control actions are indeterminate by design. Appliance,
project, contract, plugin-provided, and otherwise unknown action semantics are
unsupported in this increment.

## Loading and ordering

`RuleActionProvider` makes one GLPI Query Builder request for the IDs of the
rules actually evaluated. Rows are grouped by `rules_id` and ordered by
`rules_id`, then action `id`, reproducing the per-rule order of native
`RuleAction::getRuleActions()` without an action-query N+1.

## Sequential rules, ADD, and UPDATE

RuleTicket uses previous rule output as the next rule input. Clarus always
reconstructs only a narrow, internal and immutable subset of those intermediate
contexts; `InspectionOptions::includeActions` controls action reflection
presentation, not sequential semantics. Scalar assignments, category assignment
with its resolved category code, and exact deadline deletes are applied in the
native action order after a `MATCH`. Unknown, unsupported, or indeterminate
effects are never guessed; they taint their known criterion target and reviewed
derived dependencies for the next step instead. Requester effects also taint
requester groups, location, and profile; the category-code regex alias taints
both category ID and category code. `ProjectedRuleEffect` distinguishes applied, not-applied,
indeterminate, and unsupported outcomes with safe previous/next values for
tests only.

The projection is not action execution and does not claim historical causality.
Reflection still uses the persisted snapshot, so a later rule or manual change
can make an earlier action appear reflected or not reflected. ONADD and
ONUPDATE remain separate chains and begin from independent snapshots.

The sequential overwrite diagnostic consumes projected effects separately from
action reflection and primary rule evaluation. It may report a possible or a
confirmed simulated overwrite, but neither result claims that rules executed
historically.
