<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

$root = dirname(__DIR__);
$excluded = [
    $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
];
/** @var RecursiveDirectoryIterator<SplFileInfo> $directory */
$directory = new RecursiveDirectoryIterator($root);
$files = new RecursiveIteratorIterator($directory);
$exitCode = 0;

foreach ($files as $file) {
   if (!$file instanceof SplFileInfo) {
       continue;
   }

   if (!$file->isFile() || $file->getExtension() !== 'php') {
       continue;
   }

    $path = $file->getPathname();
   foreach ($excluded as $directory) {
      if (strpos($path, $directory) === 0) {
          continue 2;
      }
   }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    passthru($command, $result);
   if ($result !== 0) {
       $exitCode = $result;
   }
}

exit($exitCode);
