<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use PHPUnit\Framework\TestCase;

final class LocalizationCatalogTest extends TestCase
{
   public function testGettextCatalogsContainTheClarusUiStrings(): void {
      $root = dirname(__DIR__);
      $pot = file_get_contents($root . '/locales/clarus.pot');
      $ptBr = file_get_contents($root . '/locales/pt_BR.po');

      self::assertIsString($pot);
      self::assertIsString($ptBr);
      self::assertStringContainsString('msgid "Rule inspection"', $pot);
      self::assertStringContainsString('No configured action was executed.', $pot);
      self::assertStringContainsString('Current-state diagnostic only.', $pot);
      self::assertStringContainsString('msgstr "Inspeção de regras"', $ptBr);
      self::assertStringContainsString('msgstr "Atualizar inspeção"', $ptBr);
      self::assertStringContainsString('msgstr "Não corresponde"', $ptBr);
   }

   public function testCompiledCatalogsHaveTheGettextMagicNumber(): void {
      $root = dirname(__DIR__);

      foreach (['en_GB.mo', 'pt_BR.mo'] as $catalog) {
         $header = file_get_contents($root . '/locales/' . $catalog, false, null, 0, 4);
         self::assertSame("\xDE\x12\x04\x95", $header, $catalog . ' must be a gettext MO file.');
      }
   }
}
