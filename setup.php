<?php

// SPDX-License-Identifier: GPL-3.0-or-later

define('PLUGIN_CLARUS_VERSION', '0.1.0');
define('PLUGIN_CLARUS_MIN_GLPI_VERSION', '10.0.20');
define('PLUGIN_CLARUS_MAX_GLPI_VERSION', '11.0.0');
define('PLUGIN_CLARUS_MIN_PHP_VERSION', '8.1.0');
define('PLUGIN_CLARUS_MAX_PHP_VERSION', '8.4.0');

/**
 * Initialize Clarus hooks.
 *
 * Feature-specific hooks are registered by their corresponding implementation
 * phases to keep the bootstrap independent of unfinished functionality.
 */
function plugin_init_clarus(): void {
    global $PLUGIN_HOOKS;

    /** @var array<string, array<string, bool>> $PLUGIN_HOOKS */
    $PLUGIN_HOOKS['csrf_compliant']['clarus'] = true;
}

/**
 * Return Clarus metadata used by GLPI's plugin manager.
 *
 * @return array{
 *     name: string,
 *     version: string,
 *     author: string,
 *     license: string,
 *     homepage: string,
 *     requirements: array{
 *         glpi: array{min: string, max: string},
 *         php: array{min: string, max: string}
 *     }
 * }
 */
function plugin_version_clarus(): array {
    return [
        'name'         => 'Clarus',
        'version'      => PLUGIN_CLARUS_VERSION,
        'author'       => 'Renato Valadares',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/renatovaladares85/clarus',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CLARUS_MIN_GLPI_VERSION,
                'max' => PLUGIN_CLARUS_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => PLUGIN_CLARUS_MIN_PHP_VERSION,
                'max' => PLUGIN_CLARUS_MAX_PHP_VERSION,
            ],
        ],
    ];
}

/**
 * Check whether the running GLPI version is supported.
 */
function plugin_clarus_check_prerequisites(): bool {
   if (!defined('GLPI_VERSION')) {
       return false;
   }

    $glpiVersion = constant('GLPI_VERSION');
   if (!is_string($glpiVersion)) {
       return false;
   }

    return version_compare($glpiVersion, PLUGIN_CLARUS_MIN_GLPI_VERSION, '>=')
        && version_compare($glpiVersion, PLUGIN_CLARUS_MAX_GLPI_VERSION, '<');
}

/**
 * Clarus has no mandatory post-install configuration in the foundation phase.
 */
function plugin_clarus_check_config(bool $verbose = false): bool {
    return true;
}
