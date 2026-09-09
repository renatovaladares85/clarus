<?php

// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Install Clarus.
 *
 * The profile right is registered for every profile with the native zero mask.
 */
function plugin_clarus_install(): bool {
   if (!\GlpiPlugin\Clarus\Profile::registerRights()) {
       return false;
   }

    \GlpiPlugin\Clarus\ClarusConfig::installDefaults();

    return true;
}

/**
 * Uninstall Clarus.
 *
 * Only Clarus-owned profile rights are removed.
 */
function plugin_clarus_uninstall(): bool {
    $rightsRemoved = \GlpiPlugin\Clarus\Profile::unregisterRights();
    \GlpiPlugin\Clarus\ClarusConfig::remove();

    return $rightsRemoved;
}
