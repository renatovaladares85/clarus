# RuleTicket replay and evidence boundary

## Scope

The replay foundation keeps four diagnostic dimensions separate:

1. **Current snapshot compatibility** evaluates the saved Ticket as it exists
   now. It is useful for troubleshooting, but is never historical proof.
2. **Persisted history** is a raw GLPI `glpi_logs` before/after value. It is
   factual only for the field, ordering, and retained log row.
3. **Reconstructed replay** starts only from durable historical values and
   applies the characterized, pure subset of RuleTicket action semantics in
   native candidate order.
4. **Action/current-state comparison** remains an opt-in observation that a
   current value matches a configured action; it does not attribute causality.

The runtime never calls `Rule::process()`, `RuleCollection::processAllRules()`,
or `RuleTicket::executeActions()`.

## Pipeline

```text
Persisted Ticket -> TicketContextBuilder -> current snapshot inspection
        |
        +-> TicketTimelineReader -> ExecutionWindowReconstructor
                                      |
                                      v
                         RuleTicketReplayEngine
                                      |
                                      v
                  replay trace and simulated overwrite diagnostics
```

`RuleTicketCandidateProvider` remains the only candidate-selection seam. It
uses GLPI's `RuleTicketCollection`, preserving active filtering, condition,
entity inheritance, entity level, and ranking order. Clarus does not recreate
that query or sort the collection.

## Timeline and reconstruction

`TicketTimelineReader` reads only raw history fields whose search option maps
unambiguously to a known RuleTicket key. Direct Ticket columns retain their raw
stored values. Dropdown history is accepted only when GLPI's `name (id)` form
has an unambiguous trailing numeric identifier. Localized history text, actor
labels, and ambiguous values are not parsed into replay facts.

The reconstructor starts by making every current value unknown. The persisted
Ticket entity is retained only as the native collection-selection context; a
retained `entities_id` history row replaces it for the reconstructed earlier or
later window. For every other field, the oldest retained `before` value is used
as the earliest defensible value. Fields with no durable before/after evidence
remain indeterminate. This is deliberately conservative: history retention,
transient inputs, and execution boundaries may be incomplete. A deterministic
replay is an inference, never a claim that GLPI executed a rule.

ONADD has one earliest-retained-state candidate. Each chronological timestamp
group of later persisted changes creates a separate ONUPDATE candidate whose
input is the defensible state immediately before that group. Timestamp grouping
is only evidence of a later Ticket change; its `boundaryKnown` flag remains
false and never asserts that GLPI executed RuleTicket at that point. The engine
therefore exposes separate context traces without treating a likely grouping as
historical confirmation.

## Replay semantics

For each window, the engine obtains the native collection using the reconstructed
entity value, then evaluates criteria with the existing safe native
`checkCriterias()` path. Its pure input adapter mirrors the GLPI 10.0.20
RuleTicket preparation at the initial input and after every rule output. It
derives mail aliases only from a durable header and fails closed when historical
requester group membership or category-code state is not persisted. The engine
then uses `RuleEffectProjector` only for characterized scalar/category
assignments and exact deadline deletions. Output from a matched rule becomes the
next replay input. Unsupported, dynamic, transient, or indeterminate action
effects taint only their known affected context keys.

`_stop_rules_processing = 1` is characterized from GLPI 10.0.20 as a native
collection termination signal. The replay records and honors that signal when
the configured action is exactly reproducible. Any other stop-processing form
ends the replay as indeterminate rather than allowing later rules to appear
reachable.

Overwrite diagnostics consume only the replay trace. A confirmed simulated
overwrite therefore means a deterministic transition in a reconstructed
window, not that a historical rule execution is proven. Raw durable history is
also exposed separately as `LaterTicketChange`, classified as not attributable
to a rule unless future evidence can prove an execution window. Later Ticket
changes and separate ONUPDATE executions are never collapsed into a
same-execution overwrite.

## Security and limits

The replay has no session, presenter, Twig, JavaScript, or authorization
responsibility. Existing Ticket visibility and Clarus rights remain at the
entrypoint, and the sensitive-value boundary still applies before rendering.
No Ticket, history, rules, actions, actors, deadlines, or new audit records are
written by inspection.
