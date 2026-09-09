# Changelog

All notable changes to Clarus are documented in this file.

## Unreleased

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
