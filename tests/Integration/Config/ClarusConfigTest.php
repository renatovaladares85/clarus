<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Integration\Config;

use GlpiPlugin\Clarus\ClarusConfig;
use PHPUnit\Framework\TestCase;

/** @group glpi-integration */
final class ClarusConfigTest extends TestCase
{
   /** @var array<string, mixed> */
   private array $original = [];

   private string $unrelatedKey;

   protected function setUp(): void {
       parent::setUp();
       $this->original = \Config::getConfigurationValues(
           ClarusConfig::CONTEXT,
           array_keys(ClarusConfig::defaults())
       );
       $this->unrelatedKey = 'test_' . str_replace('.', '', uniqid('', true));
   }

   protected function tearDown(): void {
       \Config::deleteConfigurationValues(ClarusConfig::CONTEXT, array_keys(ClarusConfig::defaults()));
      if ($this->original !== []) {
          \Config::setConfigurationValues(ClarusConfig::CONTEXT, $this->original);
      }
       \Config::deleteConfigurationValues(ClarusConfig::CONTEXT, [$this->unrelatedKey]);
       parent::tearDown();
   }

   public function testInstallRetryKeepsExistingValuesAndAddsMissingDefaults(): void {
       \Config::deleteConfigurationValues(ClarusConfig::CONTEXT, array_keys(ClarusConfig::defaults()));
       \Config::setConfigurationValues(ClarusConfig::CONTEXT, [ClarusConfig::PAGE_SIZE => 50]);

       ClarusConfig::installDefaults();
       ClarusConfig::installDefaults();
       $config = ClarusConfig::get();

       self::assertSame(50, $config[ClarusConfig::PAGE_SIZE]);
       self::assertSame(1000, $config[ClarusConfig::RULE_LIMIT]);
       self::assertTrue($config[ClarusConfig::INCLUDE_ONADD]);
   }

   public function testValidatedUpdatePersistsTypedSettings(): void {
       ClarusConfig::update([
           ClarusConfig::AUTO_INSPECTION => '0',
           ClarusConfig::INCLUDE_ONADD => '1',
           ClarusConfig::INCLUDE_ONUPDATE => '0',
           ClarusConfig::INCLUDE_ACTIONS => '1',
           ClarusConfig::RULE_LIMIT => '2500',
           ClarusConfig::PAGE_SIZE => '100',
           ClarusConfig::INITIAL_GROUP => 'result',
       ]);

       $config = ClarusConfig::get();
       self::assertFalse($config[ClarusConfig::AUTO_INSPECTION]);
       self::assertFalse($config[ClarusConfig::INCLUDE_ONUPDATE]);
       self::assertSame(2500, $config[ClarusConfig::RULE_LIMIT]);
       self::assertSame(100, $config[ClarusConfig::PAGE_SIZE]);
       self::assertSame('result', $config[ClarusConfig::INITIAL_GROUP]);
   }

   public function testRemovalPreservesUnrelatedConfigurationInTheSameContext(): void {
       ClarusConfig::installDefaults();
       \Config::setConfigurationValues(ClarusConfig::CONTEXT, [$this->unrelatedKey => 'preserved']);

       ClarusConfig::remove();
       $remaining = \Config::getConfigurationValues(ClarusConfig::CONTEXT, [$this->unrelatedKey]);

       self::assertSame('preserved', $remaining[$this->unrelatedKey]);
       self::assertSame([], \Config::getConfigurationValues(
           ClarusConfig::CONTEXT,
           array_keys(ClarusConfig::defaults())
       ));
   }
}
