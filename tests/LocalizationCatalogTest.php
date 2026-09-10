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
      self::assertStringContainsString('Current-state diagnostic of the last saved Ticket data only.', $pot);
      self::assertStringContainsString('Unsaved form changes are not included in this diagnostic.', $pot);
      self::assertStringContainsString('Confirmed adherence', $pot);
      self::assertStringContainsString('Some criteria cannot be evaluated safely using this Ticket snapshot.', $pot);
      self::assertStringContainsString('msgstr "Inspeção de regras"', $ptBr);
      self::assertStringContainsString('msgstr "Atualizar inspeção"', $ptBr);
      self::assertStringContainsString('msgstr "Não corresponde"', $ptBr);
      self::assertStringContainsString('msgstr "Aderência confirmada"', $ptBr);
      self::assertStringContainsString('msgstr "Filtros de resultado"', $ptBr);
      self::assertStringContainsString('msgstr "Filtros de entidade"', $ptBr);
   }

   public function testCompiledCatalogsHaveTheGettextMagicNumber(): void {
      $root = dirname(__DIR__);

      foreach (['en_GB.mo', 'pt_BR.mo'] as $catalog) {
         $header = file_get_contents($root . '/locales/' . $catalog, false, null, 0, 4);
         self::assertSame("\xDE\x12\x04\x95", $header, $catalog . ' must be a gettext MO file.');
      }
   }
}
