<?php

// SPDX-License-Identifier: GPL-3.0-or-later

namespace GlpiPlugin\Clarus\Tests;

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
   public function testPluginMetadataIsDefined(): void {
       self::assertSame('0.1.0', PLUGIN_CLARUS_VERSION);
       self::assertSame('10.0.20', PLUGIN_CLARUS_MIN_GLPI_VERSION);
       self::assertSame('11.0.0', PLUGIN_CLARUS_MAX_GLPI_VERSION);
       self::assertSame('8.1.0', PLUGIN_CLARUS_MIN_PHP_VERSION);
       self::assertSame('8.4.0', PLUGIN_CLARUS_MAX_PHP_VERSION);
       self::assertTrue(function_exists('plugin_init_clarus'));
       self::assertTrue(function_exists('plugin_clarus_install'));
       self::assertTrue(function_exists('plugin_clarus_uninstall'));
   }

   public function testPluginMetadataTargetsGlpiTen(): void {
       $metadata = plugin_version_clarus();

       self::assertSame('Clarus', $metadata['name']);
       self::assertSame('GPL-3.0-or-later', $metadata['license']);
       self::assertSame('10.0.20', $metadata['requirements']['glpi']['min']);
       self::assertSame('11.0.0', $metadata['requirements']['glpi']['max']);
       self::assertSame('8.1.0', $metadata['requirements']['php']['min']);
       self::assertSame('8.4.0', $metadata['requirements']['php']['max']);
   }
}
