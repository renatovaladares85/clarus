# Clarus

Clarus is a GLPI 10 plugin that explains how active Ticket business rules
match the current, reconstructable state of a Ticket. It is diagnostic and
read-only: it never executes configured rule actions and never changes the
Ticket being inspected.

## Compatibility

- GLPI `>= 10.0.20` and `< 11.0.0`
- PHP `>= 8.1` and `< 8.4`

## Development status

`0.1.0` is under development. The initial MVP targets Ticket (`RuleTicket`)
business-rule inspection only; it does not simulate or record rule execution.

## Installation

Place the plugin directory at `glpi/plugins/clarus`, then install and activate
it through **Setup > Plugins**. Do not rename the directory.

## Security model

Inspection will require both native GLPI access to the specific Ticket and a
Clarus profile right. A matching rule is not evidence that the rule executed
historically.

## Development checks

```bash
composer install
composer qa
```

Integration checks run in GitHub Actions against GLPI 10.0.x. No GLPI core or
third-party plugin code is modified by Clarus.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
## Architecture research

The executable RuleTicket characterization that informs the future inspector is documented in [the Phase 2 technical spike](docs/architecture/rule-ticket-technical-spike.md).
