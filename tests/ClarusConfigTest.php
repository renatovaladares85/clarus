<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Clarus\ClarusConfig;
use PHPUnit\Framework\TestCase;

final class ClarusConfigTest extends TestCase
{
   protected function setUp(): void {
       \Config::$values = [];
       parent::setUp();
   }

   protected function tearDown(): void {
       \Config::$values = [];
       parent::tearDown();
   }

   public function testDefaultsAreInstalledWithoutOverwritingExistingValues(): void {
       \Config::setConfigurationValues(ClarusConfig::CONTEXT, [
           ClarusConfig::PAGE_SIZE => 50,
           'unrelated_key' => 'preserved',
       ]);

       ClarusConfig::installDefaults();
       ClarusConfig::installDefaults();

       self::assertSame(50, ClarusConfig::get()[ClarusConfig::PAGE_SIZE]);
       self::assertSame('preserved', \Config::$values[ClarusConfig::CONTEXT]['unrelated_key']);
       self::assertTrue(ClarusConfig::get()[ClarusConfig::INCLUDE_ACTIONS]);
   }

   public function testValidatedConfigurationCanBeUpdatedAndRead(): void {
       ClarusConfig::update($this->validInput([
           ClarusConfig::AUTO_INSPECTION => '0',
           ClarusConfig::RULE_LIMIT => '2500',
           ClarusConfig::PAGE_SIZE => '50',
           ClarusConfig::INITIAL_GROUP => 'entity',
       ]));

       $config = ClarusConfig::get();
       self::assertFalse($config[ClarusConfig::AUTO_INSPECTION]);
       self::assertSame(2500, $config[ClarusConfig::RULE_LIMIT]);
       self::assertSame(50, $config[ClarusConfig::PAGE_SIZE]);
       self::assertSame('entity', $config[ClarusConfig::INITIAL_GROUP]);
   }

   /** @dataProvider invalidInputProvider */
   public function testInvalidConfigurationIsRejected(string $key, mixed $value): void {
       $this->expectException(\InvalidArgumentException::class);
       ClarusConfig::update($this->validInput([$key => $value]));
   }

   /** @return iterable<array{string, mixed}> */
   public static function invalidInputProvider(): iterable {
       yield 'zero limit' => [ClarusConfig::RULE_LIMIT, '0'];
       yield 'excessive limit' => [ClarusConfig::RULE_LIMIT, '5001'];
       yield 'open page size' => [ClarusConfig::PAGE_SIZE, '30'];
       yield 'unknown grouping' => [ClarusConfig::INITIAL_GROUP, 'random'];
       yield 'invalid condition toggle' => [ClarusConfig::INCLUDE_ONADD, 'yes'];
   }

   public function testRemovalDeletesOnlyClarusOwnedKeys(): void {
       ClarusConfig::installDefaults();
       \Config::setConfigurationValues(ClarusConfig::CONTEXT, ['unrelated_key' => 'preserved']);

       ClarusConfig::remove();

       self::assertSame(
           ['unrelated_key' => 'preserved'],
           \Config::$values[ClarusConfig::CONTEXT]
       );
   }

   public function testPluginLifecycleCreatesDefaultsAndRemovesOwnedConfiguration(): void {
       \Config::setConfigurationValues(ClarusConfig::CONTEXT, ['unrelated_key' => 'preserved']);

       self::assertTrue(plugin_clarus_install());
       self::assertSame(25, ClarusConfig::get()[ClarusConfig::PAGE_SIZE]);
       self::assertTrue(plugin_clarus_uninstall());
       self::assertSame('preserved', \Config::$values[ClarusConfig::CONTEXT]['unrelated_key']);
   }

   public function testConfigurationTemplateContainsOnlyValidatedSettingsAndCsrf(): void {
       $config = ClarusConfig::get();
       $html = TemplateRenderer::getInstance()->render('@clarus/config.html.twig', [
           'action' => '/plugins/clarus/front/config.php',
           'csrfToken' => 'csrf-token',
           'config' => $config,
           'error' => null,
           'maxRuleLimit' => ClarusConfig::MAX_RULE_LIMIT,
           'pageSizeOptions' => ClarusConfig::PAGE_SIZE_OPTIONS,
           'groupOptions' => [
               'processing' => 'Processing order',
               'result' => 'Result',
               'entity' => 'Entity',
           ],
           'booleanFields' => [[
               'name' => ClarusConfig::AUTO_INSPECTION,
               'label' => 'Automatic inspection',
               'checked' => true,
           ]],
           'labels' => [
               'title' => 'Clarus configuration',
               'description' => 'Description',
               'ruleLimit' => 'Rule limit',
               'ruleLimitHint' => 'Rule limit hint',
               'pageSize' => 'Page size',
               'initialGroup' => 'Initial grouping',
               'save' => 'Save',
           ],
       ]);

       self::assertStringContainsString('name="_glpi_csrf_token" value="csrf-token"', $html);
       self::assertStringContainsString('name="inspection_rule_limit" value="1000"', $html);
       self::assertStringContainsString('name="inspection_page_size"', $html);
       self::assertStringNotContainsString('Module active', $html);
   }

   /**
    * @param array<string, mixed> $overrides
    * @return array<string, mixed>
    */
   private function validInput(array $overrides = []): array {
       return array_replace([
           ClarusConfig::AUTO_INSPECTION => '1',
           ClarusConfig::INCLUDE_ONADD => '1',
           ClarusConfig::INCLUDE_ONUPDATE => '1',
           ClarusConfig::INCLUDE_ACTIONS => '1',
           ClarusConfig::RULE_LIMIT => '1000',
           ClarusConfig::PAGE_SIZE => '25',
           ClarusConfig::INITIAL_GROUP => 'processing',
       ], $overrides);
   }
}
