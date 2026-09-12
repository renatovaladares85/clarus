# Changelog

All notable changes to Clarus are documented in this file.

## Unreleased

- Prepare the 1.0.0-dev.3 development line with a compact, responsive Ticket
  inspection presentation for sequential diagnostics. It adds safe conflict
  filtering, effective processing-order display and sorting, expanded
  rule-chain context, collapsed technical metadata, and accessible disclosure
  state without changing Inspector semantics or implying historical execution.

- Fix GLPI 10 CSRF handling for Clarus configuration saves and asynchronous
  inspection refreshes by relying on the native request bootstrap and sending
  the refresh form token in the required header.

- Prepare the 1.0.0-dev.2 development line with read-only sequential RuleTicket
  context projection and independent possible/confirmed overwrite diagnostics.
  Deterministic scalar/category assignments and exact deadline deletes can
  inform a subsequent rule without executing native actions; unsupported or
  indeterminate effects fail closed for known targets and cannot confirm an
  overwrite.

- Add a separate, deny-by-default sensitive inspection-content Profile right,
  a global ONADD/ONUPDATE evaluation budget, manual inspection loading, and
  clarified non-evaluated rule terminology.

- Add confirmed criterion-adherence percentages, indeterminate counts,
  combinable display filters, three-level deterministic sorting, and pagination
  after filtering and ordering to the Ticket inspection UI.
- Add entity filtering, exact-ratio adherence filtering and ordering, and an
  explicit last-saved-state notice to the Ticket inspection UI.
- Add native configuration-right regression coverage and JavaScript UI behavior
  checks for combined filters, deterministic ordering, and pagination.
- Add validated `plugin:clarus` defaults for minimum adherence and sort chains,
  and evaluate ONUPDATE criteria from the current safe Ticket snapshot instead
  of treating every update rule as indeterminate.
- Redesign the Ticket rule inspection tab with a safe Twig presenter, compact
  result summary, search, three-state filters, grouping, client-side pagination,
  in-place refresh, responsive diagnostic cards, and sanitized failure states.
- Add administrator-managed Clarus inspection settings in `plugin:clarus`, with
  idempotent defaults, strict validation, CSRF protection, and lifecycle cleanup.
- Add declared and CI-validated PHP 8.4 support while keeping PHP 8.5 unsupported.
- Add gettext localization for the Clarus Ticket inspection UI, including
  English and Brazilian Portuguese catalogs and reproducible catalog tooling.
- Add the authorized Ticket Rule inspection tab with server-rendered current-state
  diagnostics, read-only action reflection, and explicit ADD/UPDATE limitations.
- Add the deny-by-default `plugin_clarus_inspect` Profile right and the
  authorization boundary required before Ticket rule inspection.
- Add native Profile lifecycle handling, permissions integration coverage, and
  production-artifact upgrade validation.
- Initial plugin foundation for the `0.1.0` development line.
- Add executable RuleTicket characterization evidence for the Phase 3 design.
- Add the read-only RuleTicket Inspector core with explicit three-state results,
  native candidate selection/evaluation, configurable limits, and GLPI integration coverage.
- Add opt-in read-only RuleTicket action analysis with explicit support and
  reflection states, batched action loading, and no historical causality claim.
