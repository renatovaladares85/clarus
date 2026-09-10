# Clarus

Clarus is a GLPI 10 plugin that explains how active Ticket business rules
match the current, reconstructable state of a Ticket. It is diagnostic and
read-only: it never executes configured rule actions and never changes the
Ticket being inspected.

## Compatibility

- GLPI `>= 10.0.20` and `< 11.0.0`
- PHP `>= 8.1` and `< 8.5`

## Development status

`0.4.0` is under development. The initial MVP targets Ticket (`RuleTicket`)
business-rule inspection only; it does not simulate or record rule execution.

The Phase 3 inspector core can reconstruct persisted and safely derived Ticket
context, select native RuleTicket candidates, and report `MATCH`, `NO_MATCH`,
or `INDETERMINATE` per criterion and rule. `INDETERMINATE` is used whenever a
historical/transient value cannot be reconstructed without guessing.
Action analysis is opt-in and reports only whether supported configured effects
are reflected in the current snapshot; it does not execute actions or attribute
historical causality.

The Ticket tab presents ONADD and ONUPDATE diagnostics in one responsive view.
It evaluates the last saved Ticket state; unsaved form changes are explicitly not included.
Each card keeps its native semantic result and also shows confirmed adherence as
`percentage (matching/configured criteria)`; indeterminate criteria remain in
the denominator and are identified separately. An OR rule can therefore match
with low adherence. Search, multi-select result, condition, and entity filters, a
display-only minimum-adherence threshold, three-level sorting, grouping, and
pagination act only on the already evaluated cards. Expected, observed,
configured, and current values are shown only when the backend has explicitly
classified them as presentation-safe.

## Configuration

Administrators with the native GLPI configuration update right can open Clarus
from **Setup > Plugins**. Settings control automatic inspection, ONADD/ONUPDATE
coverage, configured-action analysis, the evaluated-rule limit, rules per page,
initial grouping, default minimum adherence, and a validated default sort chain.
The threshold and ordering are presentation defaults: they never change native
candidate selection or engine evaluation. Settings are stored in GLPI's
`plugin:clarus` configuration context; Clarus does not create a configuration
table.

## Installation

Place the plugin directory at `glpi/plugins/clarus`, then install and activate
it through **Setup > Plugins**. Do not rename the directory.

## Security model

Inspection requires both native GLPI access to the specific Ticket and the
`plugin_clarus_inspect` Profile right with the `READ` mask. It is denied by
default, including for Super-Admin, until explicitly granted. A matching rule
is not evidence that the rule executed historically.

## Development checks

```bash
composer install
composer qa
```

### Localization catalogs

Clarus-owned UI strings use the `clarus` gettext domain. Update the source and
Portuguese catalogs after changing those strings, then compile the normal GLPI
catalog artifacts:

```bash
composer run locales:update
composer run locales:compile
```

`locales/en_GB.mo` is the English fallback and `locales/pt_BR.mo` is loaded by
GLPI when Brazilian Portuguese is selected. Native GLPI labels are provided by
GLPI and are not duplicated in Clarus catalogs.

Integration checks run in GitHub Actions against GLPI 10.0.x. No GLPI core or
third-party plugin code is modified by Clarus.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
## Architecture research

The executable RuleTicket characterization that informs the future inspector is documented in [the Phase 2 technical spike](docs/architecture/rule-ticket-technical-spike.md).
The implemented read-only pipeline and its limits are documented in [the Inspector core architecture](docs/architecture/inspector-core.md).
The supported action semantics and their read-only limits are documented in [the action analysis architecture](docs/architecture/action-analysis.md).
The Profile right, lifecycle, and future UI authorization contract are documented
in [the permissions architecture](docs/architecture/permissions.md).
The Ticket UI, administrative settings, refresh endpoint, and value-presentation
boundary are documented in [the inspection UI architecture](docs/architecture/inspection-ui.md).
