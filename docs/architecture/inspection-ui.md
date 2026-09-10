# Rule inspection UI and configuration

## Scope and semantics

The Ticket tab combines enabled ONADD and ONUPDATE inspections into one
diagnostic view. It preserves the Inspector engine's three results exactly:
`MATCH`, `NO_MATCH`, and `INDETERMINATE`. Criterion proportions such as `4/5`
are explanatory metadata, never a fourth result or a score.

The UI is a current-state diagnostic. It does not call native processing or
action-execution methods and does not claim that a rule or action ran in the
past. ONUPDATE criteria use the current safe Ticket snapshot just like ONADD;
they are indeterminate only when a criterion cannot be evaluated safely, not
merely because an original update change set is unavailable.

Confirmed adherence is a presentation metric: matching criteria divided by all
configured criteria, rounded to a whole percentage. Indeterminate criteria stay
in the denominator and are also counted separately. It never replaces the
three-state engine result; in particular, an OR rule can be `MATCH` with a low
adherence percentage. Rules without criteria show `0% (0/0)` and retain their
engine `INDETERMINATE` result.

## Rendering and presentation safety

`InspectionPresenter` converts immutable Inspector DTOs into a view model before
Twig receives them. Rule metadata and translated labels are escaped by Twig.
Criterion values require both the Inspector safety flag and an explicit central
allowlist of reviewed Ticket fields. New or unknown criterion keys are denied
by default. Action values require the analyzer safety flag and a supported
numeric representation. Other values are replaced by neutral omission text;
objects and arbitrary structures never reach the template.

`InspectionRenderer` uses GLPI's `TemplateRenderer` and the `@clarus` Twig
namespace. Bootstrap and Tabler supplied by GLPI provide the base components.
Clarus CSS is fully scoped below `.clarus-inspection` or `.clarus-config`.
Vanilla JavaScript adds search, multi-select result and condition filters, a
display-only adherence threshold, up to three sort levels, grouping,
presentation-only pagination, and in-place refresh. Filters use OR within each
group and AND across groups. Sorting has deterministic ranking and ID fallbacks;
grouping only adds visual headings and never overrides sort order. All evaluated
rules remain in the server-rendered result; pagination never changes the engine
input or output.

## Configuration lifecycle

`ClarusConfig` owns these keys in the `plugin:clarus` context of `glpi_configs`:

- automatic inspection;
- ONADD inspection;
- ONUPDATE inspection;
- configured-action analysis;
- evaluated-rule limit;
- rules per page;
- initial grouping.
- default minimum adherence;
- default sort chain of up to three unique fields and directions.

Install and upgrade retries add only missing defaults. Updates accept only the
defined booleans, a positive rule limit no greater than 5000, page sizes from a
closed list, known grouping values, an integer adherence threshold from 0 to
100, and unique sort fields from a closed list. Uninstall removes only
Clarus-owned keys and preserves unrelated rows in the same context.

## Authorization and refresh

The configuration page requires the native `config` right with `UPDATE` and
validates CSRF before saving. The refresh endpoint accepts POST only, validates
CSRF, validates the Ticket ID, and repeats both authorization checks:
`plugin_clarus_inspect` with `READ` and `Ticket::canViewItem()`. Error responses
shown to the user are generic and do not contain exceptions, SQL, paths, or
stack traces.
