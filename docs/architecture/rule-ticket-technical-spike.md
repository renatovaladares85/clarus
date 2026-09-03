# RuleTicket technical spike

## Scope and sources

This is a Phase 2 executable technical spike that informs Phase 3; it is not an inspector implementation. Evidence comes from GLPI `10.0.20` (`203af98aa1088d561946033c05191e3306d494bb`), `10.0/bugfixes` (`ecd49e6bc473c3373a6051564874b9e6ee399492`), the native files `Ticket.php`, `RuleTicket.php`, `RuleTicketCollection.php`, `RuleCollection.php`, `Rule.php`, `RuleCriteria.php`, `RuleAction.php`, and GLPI's `phpunit/functional/RuleTicketTest.php`. Clarus runs `tests/Integration/RuleTicket/RuleTicketCharacterizationTest.php` explicitly in dedicated jobs on GLPI 10.0.20/PHP 8.1 and current 10.0.x/PHP 8.3.

Unless noted, statements below are **CONFIRMED — GLPI NATIVO**.

## Native flow

`Ticket::prepareInputForAdd()` enriches input, calls private `fillInputForBusinessRules()`, builds `RuleTicketCollection($input['entities_id'])`, then calls `processAllRules($input, $input, ['recursive' => true], ['condition' => RuleTicket::ONADD])`. The returned output continues normal Ticket creation.

`Ticket::prepareInputForUpdate()` starts from changed input, supplements category code, existing actors, contract types and selected persisted fields, computes changed criteria, and calls the same collection only when changes exist, with `RuleTicket::ONUPDATE` and `only_criteria`. It is not a full Ticket snapshot.

`RuleCollection::getCollectionDatas(true, true, $condition)` filters `glpi_rules` by `is_active = 1`, `sub_type = RuleTicket`, and `condition & requested_condition`, then hydrates rules via `Rule::getRuleWithCriteriasAndActions()`. For RuleTicket, entity inheritance comes from `getEntitiesRestrictCriteria()`; ordering is `glpi_entities.level ASC`, then `ranking ASC`. There is no runtime collection limit; UI-only `getCollectionPart()` has an optional limit.

## Input and criteria

`RuleTicket::getCriterias()` is authoritative. Keys: `name`, `content`, `date_mod`, `itilcategories_id`, `itilcategories_id_code`, `type`, `users_id_recipient`, `_users_id_requester`, `_groups_id_of_requester`, `_locations_id_of_requester`, `_locations_id_of_item`, `_groups_id_of_item`, `_states_id_of_item`, `locations_id`, `_groups_id_requester`, `_users_id_assign`, `_groups_id_assign`, `_suppliers_id_assign`, `_users_id_observer`, `_groups_id_observer`, `requesttypes_id`, `itemtype`, `entities_id`, `profiles_id`, `urgency`, `impact`, `priority`, `status`, `_mailgate`, `_x-priority`, `_from`, `_subject`, `_reply-to`, `_in-reply-to`, `_to`, `slas_id_ttr`, `slas_id_tto`, `olas_id_ttr`, `olas_id_tto`, `_contract_types`, `global_validation`, `_date_creation_calendars_id`.

Ticket columns cover only a subset. Actor groups, requester profile/location, linked-item fields, contracts, category code, calendars and mail headers are derived/transient. Mail headers, original change set, pre-update values and earlier rule output are **INDETERMINATE FOR RETROSPECTIVE INSPECTION** from a persisted Ticket alone.

## Criteria and matching

`glpi_rules` stores `entities_id`, `sub_type`, `ranking`, `match`, `is_active`, `is_recursive`, `condition`; `glpi_rulecriterias` stores `rules_id`, `criteria`, `condition`, `pattern`; `glpi_ruleactions` stores `rules_id`, `action_type`, `field`, `value`.

`RuleCriteria::getConditions()` supplies: is (0), is not (1), contains (2), does not contain (3), begins (4), ends (5), regex match (6), regex does not match (7), exists (8), does not exist (9). Tree dropdown criteria additionally expose under (11) and not under (12). CIDR is generic only for `ip`/`subnet`, neither of which is a RuleTicket criterion. `RuleCriteria::match()` is case-insensitive for text/equality; arrays use any-match for positive conditions and all-match for negative ones.

`glpi_rules.match` is `AND` or `OR`. `Rule::checkCriterias()` short-circuits on first failure for AND and first match for OR; `Rule::process()` rejects zero criteria through `validateCriterias()`. The test suite covers selection, ADD/UPDATE conditions, entity recursion, ranking, AND/OR, all generic operators, tree/array behavior, and sequential output.

## Sequential behavior and read-only boundary

`RuleTicketCollection::$use_output_rule_process_as_next_input` is true. `RuleCollection::processAllRules()` turns each rule output into the next input. `Rule::process()` calls `checkCriterias()` and then `RuleTicket::executeActions()`. The characterization has rule A assign urgency and rule B match it before assigning impact.

Therefore `processAllRules()`, `Rule::process()`, and `RuleTicket::executeActions()` are **NOT SAFE** for Clarus runtime: they execute actions, may invoke rule hooks and intentionally mutate evolving output. They are used only in isolated CI fixtures.

### SAFE WITH CONDITIONS — `Rule::checkCriterias(array $input): bool`

It does not execute actions or write database rows; the test confirms a configured action remains unapplied. It does change in-memory result/regex state and may read dropdown/tree data. Phase 3 may use it only with a fresh rule object, a reconstructed input copy, no call to `process()`/`processAllRules()`, and an explicit **indeterminate** result when input cannot be reconstructed.

`getCollectionDatas()` is a read loading seam, but internal/cache-backed; Phase 3 must keep its use isolated and revalidate it across GLPI 10.0.x.

## Compatibility, history and external reference

The compared 10.0.x revision preserves the relevant signatures and semantics for collection filtering/order, `checkCriterias()`, and output-as-next-input. Observed changes concern priority casting, a watcher regex action field and preview behavior: **compatible for this Phase 3 contract**.

GLPI offers no evidence that a current Ticket proves historical rule execution: input, rules, activation, ranking, entities and sequential prior actions can all change. Clarus must report current reconstructed correspondence only.

The NexTools revision `58c3c654b4df29fc6af0d608ccb22265a477b988` advertises Rule Inspector but contains no reusable implementation in that revision. Classification: **PRECISA VALIDAR** for concepts; **MELHOR USAR NATIVO GLPI** for rule semantics; no code was copied.

Phase 3 must preserve native entity/ranking order and add the approved configurable inspection cap (default 1000, never a hard maximum) without silent truncation. No question blocks Phase 3.
