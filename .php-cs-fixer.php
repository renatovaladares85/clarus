<?php
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor'])
    ->ignoreVCSIgnored(true)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        'heredoc_indentation' => false,
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer/.php-cs-fixer.cache');
