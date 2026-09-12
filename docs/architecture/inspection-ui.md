# Rule inspection UI and configuration

## Scope and semantics

The Ticket tab combines enabled ONADD and ONUPDATE inspections into one
diagnostic view. It preserves the Inspector engine's three results exactly:
`MATCH`, `NO_MATCH`, and `INDETERMINATE`. Criterion proportions such as `4/5`
are explanatory metadata, never a fourth result or a score.

The UI is a current-state diagnostic of the last saved Ticket state. Unsaved
form changes are not included. It does not call native processing or
action-execution methods and does not claim that a rule or action ran in the
past. ONUPDATE criteria use the current safe Ticket snapshot just like ONADD;
they are indeterminate only when a criterion cannot be evaluated safely, not
merely because an original update change set is unavailable.

The configured rule limit is a single deterministic budget for the complete
inspection: ONADD consumes it first and ONUPDATE receives the remainder. This
keeps evaluated results at or below the configured maximum while candidate
counts and truncation still report rules outside that budget.

Confirmed adherence is a presentation metric: matching criteria divided by all
configured criteria, rounded to a whole percentage. Not evaluated criteria stay
in the denominator and are also counted separately. It never replaces the
three-state engine result; in particular, an OR rule can be `MATCH` with a low
adherence percentage. Rules without criteria show `0% (0/0)` and retain their
engine `INDETERMINATE` result.

## Rendering and presentation safety

`InspectionPresenter` converts immutable Inspector DTOs into a view model before
Twig receives it. Rule metadata and translated labels are escaped by Twig.
Potentially sensitive expected, observed, and configured-action values are
omitted unless the active profile has `plugin_clarus_show_sensitive` with
`READ`; the Inspector safety flags are still required. New or unknown criterion
keys remain denied by default. Objects and arbitrary structures never reach the
template.

`InspectionRenderer` uses GLPI's `TemplateRenderer` and the `@clarus` Twig
namespace. Bootstrap and Tabler supplied by GLPI provide the base components.
Clarus CSS is fully scoped below `.clarus-inspection` or `.clarus-config`.
Vanilla JavaScript adds search across safe rule metadata, segmented result and
conflict controls, compact condition and entity pickers, the configured
adherence threshold, up to three sort levels in a collapsed editor, grouping,
presentation-only pagination, and in-place refresh. Filters use OR within each
group and AND across groups. Conflict filters use only presenter metadata and
never receive diagnostic values. Adherence filtering and sorting use the exact
matching-criteria ratio; whole percentages are display-only. Sorting has
deterministic ranking and ID fallbacks;
grouping only adds visual headings and never overrides sort order. All evaluated
rules remain in the server-rendered result; pagination never changes the engine
input or output.

The compact rule row exposes the primary result independently from its optional
possible/confirmed overwrite badges. Expanding it shows only the relevant
rule-ID chain, affected-field label, and a safe explanation; confirmed text
explicitly remains simulation-only. Effective processing position is presented
per independent ONADD or ONUPDATE chain and can be used for presentation
sorting, but never replaces the Inspector's native sequential order. Technical
metadata is a nested disclosure, while native `<details>` controls and the
associated JavaScript keep disclosure state available to assistive technology.

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
relies on the GLPI request bootstrap to validate CSRF before saving. The refresh
endpoint accepts POST only and sends the form token in GLPI's CSRF header; the
same GLPI bootstrap rejects missing or invalid tokens before the endpoint
validates the Ticket ID and repeats both authorization checks:
`plugin_clarus_inspect` with `READ` and `Ticket::canViewItem()`. Error responses
shown to the user are generic and do not contain exceptions, SQL, paths, or
stack traces.
