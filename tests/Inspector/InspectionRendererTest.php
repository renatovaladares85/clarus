<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\ClarusConfig;
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
         ['Value cannot be reconstructed defensibly from the persisted Ticket state.'],
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

      $html = (new InspectionRenderer())->render(
         [$result],
         ClarusConfig::defaults(),
         12,
         '/plugins/clarus/ajax/inspection.php',
         'csrf-token'
      );

      self::assertStringContainsString('Current-state diagnostic of the last saved Ticket data only', $html);
      self::assertStringContainsString('Unsaved form changes are not included in this diagnostic.', $html);
      self::assertStringContainsString('On ticket update (ONUPDATE)', $html);
      self::assertStringContainsString('Indeterminate', $html);
      self::assertStringContainsString('Reflected in current state', $html);
      self::assertStringContainsString('Results were truncated', $html);
      self::assertStringContainsString('class="clarus-rule card"', $html);
      self::assertStringContainsString('data-evaluation="indeterminate"', $html);
      self::assertStringContainsString('data-adherence-numerator="0"', $html);
      self::assertStringContainsString('data-adherence-denominator="1"', $html);
      self::assertStringContainsString('0% (0/1)', $html);
      self::assertStringContainsString('data-clarus-minimum-adherence', $html);
      self::assertStringContainsString('data-clarus-result', $html);
      self::assertStringContainsString('data-clarus-condition', $html);
      self::assertStringContainsString('data-clarus-entity', $html);
      self::assertStringContainsString('data-clarus-sort-field', $html);
      self::assertStringContainsString('Some criteria cannot be evaluated safely using this Ticket snapshot.', $html);
      self::assertStringNotContainsString('\\"', $html);
      self::assertStringContainsString('&lt;rule&gt;', $html);
      self::assertStringNotContainsString('secret-pattern', $html);
      self::assertStringNotContainsString('secret-observed-value', $html);
      self::assertStringNotContainsString('secret-configured-value', $html);
      self::assertStringNotContainsString('secret-current-value', $html);
      self::assertStringContainsString('No configured action was executed', $html);
      self::assertStringNotContainsString('PARTIAL_MATCH', $html);
      self::assertStringNotContainsString('Partial', $html);
   }

   public function testRendersClarusOwnedLabelsThroughTheGettextDomain(): void {
      $GLOBALS['clarusTranslations'] = ['clarus' => [
         'Rule inspection' => 'Inspeção de regras',
         'Current-state diagnostic of the last saved Ticket data only. No rule is executed or changed.'
            => 'Diagnóstico do último estado salvo; nenhuma regra é executada ou alterada.',
         'On ticket creation (ONADD)' => 'Na criação do chamado (ONADD)',
         'Matches' => 'Corresponde',
      ]];
      $rule = new RuleInspection(
         1,
         'Rule',
         \RuleTicket::ONADD,
         0,
         true,
         1,
         'AND',
         [],
         Evaluation::MATCH
      );
      $result = new InspectionResult(12, \RuleTicket::ONADD, 1, 1, 1, false, [$rule]);

      $html = (new InspectionRenderer())->render(
         [$result],
         ClarusConfig::defaults(),
         12,
         '/plugins/clarus/ajax/inspection.php',
         'csrf-token'
      );

      self::assertStringContainsString('Inspeção de regras', $html);
      self::assertStringContainsString('Diagnóstico do último estado salvo', $html);
      self::assertStringContainsString('Na criação do chamado (ONADD)', $html);
      self::assertStringContainsString('Corresponde', $html);
   }

   public function testRendersEmptyDisabledAndTechnicalErrorAsDistinctStates(): void {
      $settings = ClarusConfig::defaults();
      $empty = new InspectionResult(12, \RuleTicket::ONADD, 1000, 0, 0, false, []);
      $renderer = new InspectionRenderer();

      $emptyHtml = $renderer->render([$empty], $settings, 12, '/refresh', 'token');
      self::assertStringContainsString('No rules were found for the enabled conditions.', $emptyHtml);

      $disabledHtml = $renderer->render([], $settings, 12, '/refresh', 'token', false);
      self::assertStringContainsString('Automatic inspection is disabled.', $disabledHtml);

      $errorHtml = $renderer->render(
         [],
         $settings,
         12,
         '/refresh',
         'token',
         true,
         'The inspection could not be completed. Try again or contact an administrator.'
      );
      self::assertStringContainsString('The inspection could not be completed.', $errorHtml);
      self::assertStringNotContainsString('stack trace', $errorHtml);
   }
}
