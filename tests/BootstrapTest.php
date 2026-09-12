<?php

// SPDX-License-Identifier: GPL-3.0-or-later

namespace GlpiPlugin\Clarus\Tests;

use GlpiPlugin\Clarus\TicketTab;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
   protected function tearDown(): void {
       \Session::$hasRight = false;
       parent::tearDown();
   }

   public function testPluginMetadataIsDefined(): void {
       self::assertSame('1.0.0-dev.3', PLUGIN_CLARUS_VERSION);
       self::assertSame('10.0.20', PLUGIN_CLARUS_MIN_GLPI_VERSION);
       self::assertSame('11.0.0', PLUGIN_CLARUS_MAX_GLPI_VERSION);
       self::assertSame('8.1.0', PLUGIN_CLARUS_MIN_PHP_VERSION);
       self::assertSame('8.5.0', PLUGIN_CLARUS_MAX_PHP_VERSION);
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
       self::assertSame('8.5.0', $metadata['requirements']['php']['max']);
   }

   public function testPluginXmlVersionMatchesRuntimeVersion(): void {
       $pluginXml = file_get_contents(dirname(__DIR__) . '/plugin.xml');

       self::assertIsString($pluginXml);
       self::assertMatchesRegularExpression(
           '#<num>' . preg_quote(PLUGIN_CLARUS_VERSION, '#') . '</num>#',
           $pluginXml
       );
   }

   public function testPluginRegistersTheProfileAndTicketTabs(): void {
      global $PLUGIN_HOOKS;

      \Plugin::$registeredClasses = [];
      $PLUGIN_HOOKS = [];
      \Session::$hasRight = true;
      plugin_init_clarus();

      self::assertSame(
         ['addtabon' => \Profile::class],
         \Plugin::registeredAttributes(\GlpiPlugin\Clarus\Profile::class)
      );
      self::assertSame(
         ['addtabon' => \Ticket::class],
         \Plugin::registeredAttributes(TicketTab::class)
      );
      /** @var array<string, array<string, mixed>> $hooks */
      $hooks = $GLOBALS['PLUGIN_HOOKS'];
      $configHook = $hooks['config_page'];
      $cssHook = $hooks['add_css'];
      $javascriptHook = $hooks['add_javascript'];
      self::assertIsArray($configHook);
      self::assertIsArray($cssHook);
      self::assertIsArray($javascriptHook);
      self::assertSame('front/config.php', $configHook['clarus']);
      self::assertSame(['css/clarus.css'], $cssHook['clarus']);
      self::assertSame(['js/inspection.js'], $javascriptHook['clarus']);
   }

   public function testConfigurationHookIsNotExposedWithoutNativeConfigUpdateRight(): void {
      global $PLUGIN_HOOKS;

      $PLUGIN_HOOKS = [];
      \Session::$hasRight = false;
      plugin_init_clarus();

      self::assertArrayNotHasKey('config_page', $PLUGIN_HOOKS);
   }

}
