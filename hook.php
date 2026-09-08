<?php

// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Install Clarus.
 *
 * The profile right is registered for every profile with the native zero mask.
 */
function plugin_clarus_install(): bool {
    return \GlpiPlugin\Clarus\Profile::registerRights();
}

/**
 * Uninstall Clarus.
 *
 * Only Clarus-owned profile rights are removed.
 */
function plugin_clarus_uninstall(): bool {
    return \GlpiPlugin\Clarus\Profile::unregisterRights();
}
