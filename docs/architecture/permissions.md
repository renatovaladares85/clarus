# Clarus Profile permissions

## Scope

Phase 5 adds authorization for the future Ticket-facing Inspector without
coupling the read-only Inspector core to session, Profile, or entity internals.

## Right and default

Clarus defines one right: `plugin_clarus_inspect`. Its only supported mask is
the GLPI-native `READ` mask. `ProfileRight::addProfileRights()` creates a row
for each Profile with the native zero mask, so no Profile — including
Super-Admin — receives access implicitly.

An administrator grants or revokes `READ` from the Clarus tab in
Administration > Profiles. The right is stored by GLPI in
`glpi_profilerights`, keyed by `profiles_id` and the right name. It is a
Profile-wide right; Clarus does not maintain a second entity ACL.

## Authorization boundary

```text
Profile right: plugin_clarus_inspect / READ
  +
Ticket::canViewItem()
  ↓
Authorization::canInspectTicket()
```

`Ticket::canViewItem()` remains the source of truth for Ticket visibility,
active entity, recursive entity access, and requester/group/assignment rights.
The Clarus right never makes a Ticket visible.

The Inspector classes remain free of Session and Profile dependencies.

## Lifecycle

`plugin_clarus_install()` delegates to `Profile::registerRights()`. It checks
the native right catalogue before calling `ProfileRight::addProfileRights()`,
which preserves existing grants on an install retry or plugin update.

`plugin_clarus_uninstall()` calls `ProfileRight::deleteProfileRights()` only
for `plugin_clarus_inspect`. Reinstall registers a fresh zero-valued right;
it never restores a prior grant.

## Phase 6 contract

The Ticket UI must call `Authorization::canInspectTicket()` before displaying
the Inspector entrypoint. Any backend endpoint must call it again before it
loads or executes the Inspector. Hiding a tab or button is not authorization.

The later inspection UI keeps this boundary on the Ticket tab and repeats it in
the POST refresh endpoint. Clarus settings use the native `config` right with
`UPDATE`; that administrative right never bypasses Ticket inspection rights.
