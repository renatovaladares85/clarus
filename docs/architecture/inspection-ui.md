# Rule inspection UI and configuration

## Scope and semantics

The Ticket tab combines enabled ONADD and ONUPDATE inspections into one
diagnostic view. It preserves the Inspector engine's three results exactly:
`MATCH`, `NO_MATCH`, and `INDETERMINATE`. Criterion proportions such as `4/5`
are explanatory metadata, never a fourth result or a score.

The UI is a current-state diagnostic. It does not call native processing or
action-execution methods and does not claim that a rule or action ran in the
past. ONUPDATE limitations remain visible because the original change set is
not available from a persisted Ticket.

## Rendering and presentation safety

`InspectionPresenter` converts immutable Inspector DTOs into a view model before
Twig receives them. Rule metadata and translated labels are escaped by Twig.
Criterion values require both the Inspector safety flag and the central Ticket
field policy. Action values require the analyzer safety flag and a supported
numeric representation. Other values are replaced by neutral omission text;
objects and arbitrary structures never reach the template.

`InspectionRenderer` uses GLPI's `TemplateRenderer` and the `@clarus` Twig
namespace. Bootstrap and Tabler supplied by GLPI provide the base components.
Clarus CSS is fully scoped below `.clarus-inspection` or `.clarus-config`.
Vanilla JavaScript adds search, filters, sorting, presentation-only pagination,
and in-place refresh. All evaluated rules remain in the server-rendered result;
pagination never changes the engine input or output.

## Configuration lifecycle

`ClarusConfig` owns these keys in the `plugin:clarus` context of `glpi_configs`:

- automatic inspection;
- ONADD inspection;
- ONUPDATE inspection;
- configured-action analysis;
- evaluated-rule limit;
- rules per page;
- initial grouping.

Install and upgrade retries add only missing defaults. Updates accept only the
defined booleans, a positive rule limit no greater than 5000, page sizes from a
closed list, and known grouping values. Uninstall removes only Clarus-owned
keys and preserves unrelated rows in the same context.

## Authorization and refresh

The configuration page requires the native `config` right with `UPDATE` and
validates CSRF before saving. The refresh endpoint accepts POST only, validates
CSRF, validates the Ticket ID, and repeats both authorization checks:
`plugin_clarus_inspect` with `READ` and `Ticket::canViewItem()`. Error responses
shown to the user are generic and do not contain exceptions, SQL, paths, or
stack traces.
