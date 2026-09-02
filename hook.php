<?php

// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Install Clarus.
 *
 * No database object is created during the foundation phase.
 */
function plugin_clarus_install(): bool
{
    return true;
}

/**
 * Uninstall Clarus.
 *
 * The foundation phase owns no persistent data.
 */
function plugin_clarus_uninstall(): bool
{
    return true;
}
