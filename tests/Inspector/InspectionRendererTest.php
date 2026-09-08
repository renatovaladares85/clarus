<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ActionEvaluation;
use GlpiPlugin\Clarus\Inspector\ActionInspection;
use GlpiPlugin\Clarus\Inspector\ActionSupport;
use GlpiPlugin\Clarus\Inspector\CriterionInspection;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionRenderer;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use PHPUnit\Framework\TestCase;

final class InspectionRendererTest extends TestCase
{
   protected function tearDown(): void {
      unset($GLOBALS['clarusTranslations']);
      parent::tearDown();
   }

   public function testRendersCurrentStateSemanticsWithoutUnsafeValues(): void {
      $criterion = new CriterionInspection(
         'content',
         2,
         'secret-pattern',
         Evaluation::INDETERMINATE,
         'missing historical input',
         true,
         'secret-observed-value'
      );
      $action = new ActionInspection(
         8,
         'assign',
         'urgency',
         ActionSupport::SUPPORTED,
         ActionEvaluation::REFLECTED,
         null,
         true,
         'secret-configured-value',
         true,
         'secret-current-value'
      );
      $rule = new RuleInspection(
         7,
         '<rule>',
         \RuleTicket::ONUPDATE,
         3,
         true,
         2,
         'AND',
         [$criterion],
         Evaluation::INDETERMINATE,
         ['UPDATE eligibility depends on the original change set.'],
         [$action]
      );
      $result = new InspectionResult(
         12,
         \RuleTicket::ONUPDATE,
         1,
         2,
         1,
         true,
         [$rule],
         ['Configured rule actions are never executed by inspection.']
      );

      $html = (new InspectionRenderer())->render($result);

      self::assertStringContainsString('Current-state diagnostic only', $html);
      self::assertStringContainsString('On ticket update (ONUPDATE)', $html);
      self::assertStringContainsString('Indeterminate', $html);
      self::assertStringContainsString('Reflected in current state', $html);
      self::assertStringContainsString('Results were truncated', $html);
      self::assertStringContainsString('<details class="mb-2">', $html);
      self::assertStringContainsString('<dt class="col-sm-4">Condition</dt>', $html);
      self::assertStringContainsString('<dd class="col-sm-8">On ticket update (ONUPDATE)</dd>', $html);
      self::assertStringNotContainsString('\\"', $html);
      self::assertStringContainsString('&lt;rule&gt;', $html);
      self::assertStringNotContainsString('secret-pattern', $html);
      self::assertStringNotContainsString('secret-observed-value', $html);
      self::assertStringNotContainsString('secret-configured-value', $html);
      self::assertStringNotContainsString('secret-current-value', $html);
      self::assertStringContainsString('not proof of historical rule execution', $html);
   }

   public function testRendersClarusOwnedLabelsThroughTheGettextDomain(): void {
      $GLOBALS['clarusTranslations'] = ['clarus' => [
         'Rule inspection' => 'Inspeção de regras',
         'Current-state diagnostic only; it is not proof of historical rule execution.'
            => 'Diagnóstico do estado atual; não prova a execução histórica de regras.',
         'On ticket creation (ONADD)' => 'Na criação do chamado (ONADD)',
         'Matches current state' => 'Corresponde ao estado atual',
      ]];
      $result = new InspectionResult(12, \RuleTicket::ONADD, 1, 0, 0, false, []);

      $html = (new InspectionRenderer())->render($result);

      self::assertStringContainsString('Inspeção de regras', $html);
      self::assertStringContainsString('Diagnóstico do estado atual', $html);
      self::assertStringContainsString('Na criação do chamado (ONADD)', $html);
   }
}
