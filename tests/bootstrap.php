<?php

// SPDX-License-Identifier: GPL-3.0-or-later

require dirname(__DIR__) . '/setup.php';
require dirname(__DIR__) . '/hook.php';

$glpiRoot = getenv('GLPI_ROOT');
if (!is_string($glpiRoot)) {
    return;
}

if (!is_file($glpiRoot . '/inc/includes.php')) {
    return;
}

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', $glpiRoot);
}

require_once $glpiRoot . '/inc/includes.php';
