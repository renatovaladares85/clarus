<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

$glpiRoot = getenv('GLPI_ROOT');
if (!is_string($glpiRoot) || !is_file($glpiRoot . '/inc/includes.php')) {
    throw new \RuntimeException('GLPI_ROOT must reference an installed GLPI instance.');
}

if (!defined('TU_USER')) {
    define('TU_USER', 1);
}

require_once $glpiRoot . '/inc/includes.php';

\Session::destroy();
\Session::start();

$auth = new \Auth();
if (!$auth->login('glpi', 'glpi', true) || !\Session::haveRight('entity', UPDATE)) {
    throw new \RuntimeException(
        'GLPI integration bootstrap could not establish an authenticated session with entity UPDATE right.'
    );
}
