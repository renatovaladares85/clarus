# Read-only RuleTicket action analysis

## Scope

Phase 4 optionally enriches each `RuleInspection` with the configured actions
of that rule and compares supported effects with the current reconstructable
Ticket snapshot. `InspectionOptions::includeActions` defaults to `false`, so
Phase 3 behavior and query cost remain unchanged unless action analysis is
requested.

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

RuleTicket uses previous rule output as the next rule input. Clarus does not
reconstruct those intermediate states. A later rule or manual change can make
an earlier action appear reflected or not reflected in the current snapshot.

ONADD and ONUPDATE actions use the same snapshot comparison. ONUPDATE criterion
eligibility remains indeterminate when the original change set is unavailable;
action reflection stays a separate dimension and does not increase historical
certainty.
