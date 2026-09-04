<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ActionEvaluation;
use GlpiPlugin\Clarus\Inspector\ActionSupport;
use GlpiPlugin\Clarus\Inspector\ConfiguredAction;
use GlpiPlugin\Clarus\Inspector\ContextValue;
use GlpiPlugin\Clarus\Inspector\RuleTicketActionAnalyzer;
use GlpiPlugin\Clarus\Inspector\TicketContext;
use PHPUnit\Framework\TestCase;

final class RuleTicketActionAnalyzerTest extends TestCase
{
   private RuleTicketActionAnalyzer $analyzer;

   protected function setUp(): void {
       $this->analyzer = new RuleTicketActionAnalyzer();
   }

   public function testIntegerAssignmentNormalizesNumericStrings(): void {
       $result = $this->analyzer->analyze(
           $this->action('assign', 'urgency', '4'),
           $this->context('urgency', 4)
       );

       self::assertSame(ActionSupport::SUPPORTED, $result->support);
       self::assertSame(ActionEvaluation::REFLECTED, $result->evaluation);
       self::assertSame(4, $result->configuredValue);
       self::assertSame(4, $result->currentValue);
   }

   public function testDifferentIntegerAssignmentIsNotReflected(): void {
       $result = $this->analyzer->analyze(
           $this->action('assign', 'impact', '5'),
           $this->context('impact', 3)
       );

       self::assertSame(ActionEvaluation::NOT_REFLECTED, $result->evaluation);
       self::assertNull($result->reason);
   }

   public function testNullIsNotNormalizedToZeroForAssignment(): void {
       $result = $this->analyzer->analyze(
           $this->action('assign', 'status', '0'),
           $this->context('status', null)
       );

       self::assertSame(ActionEvaluation::NOT_REFLECTED, $result->evaluation);
       self::assertNull($result->currentValue);
   }

    /** @dataProvider nonNullDeadlineValues */
   public function testDeleteRequiresExactNull(mixed $currentValue): void {
       $result = $this->analyzer->analyze(
           $this->action('delete', 'time_to_resolve', '1'),
           $this->context('time_to_resolve', $currentValue)
       );

       self::assertSame(ActionEvaluation::NOT_REFLECTED, $result->evaluation);
   }

   public function testDeleteIsReflectedOnlyByNull(): void {
       $result = $this->analyzer->analyze(
           $this->action('delete', 'time_to_resolve', '1'),
           $this->context('time_to_resolve', null)
       );

       self::assertSame(ActionEvaluation::REFLECTED, $result->evaluation);
   }

    /** @return iterable<array{mixed}> */
   public static function nonNullDeadlineValues(): iterable {
       yield 'zero' => [0];
       yield 'empty string' => [''];
       yield 'timestamp' => ['2026-09-04 12:00:00'];
   }

   public function testRelationUsesMembershipAndAllowsAdditionalActors(): void {
       $result = $this->analyzer->analyze(
           $this->action('assign', '_users_id_assign', '10'),
           $this->context('_users_id_assign', [8, 10, 12])
       );

       self::assertSame(ActionEvaluation::REFLECTED, $result->evaluation);
       self::assertSame([8, 10, 12], $result->currentValue);
   }

   public function testMissingRelationMemberIsNotReflected(): void {
       $result = $this->analyzer->analyze(
           $this->action('append', '_groups_id_observer', '10'),
           $this->context('_groups_id_observer', [8, 12])
       );

       self::assertSame(ActionEvaluation::NOT_REFLECTED, $result->evaluation);
   }

   public function testUnavailableCurrentValueIsIndeterminate(): void {
       $context = new TicketContext([
           'urgency' => ContextValue::indeterminate('not available'),
       ]);
       $result = $this->analyzer->analyze($this->action('assign', 'urgency', '4'), $context);

       self::assertSame(ActionSupport::SUPPORTED, $result->support);
       self::assertSame(ActionEvaluation::INDETERMINATE, $result->evaluation);
       self::assertSame(RuleTicketActionAnalyzer::REASON_CURRENT_VALUE_UNAVAILABLE, $result->reason);
   }

   public function testInvalidConfigurationIsIndeterminateWithoutExposingValue(): void {
       $result = $this->analyzer->analyze(
           new ConfiguredAction(1, 1, '', '', 'sensitive', 0),
           new TicketContext([])
       );

       self::assertSame(ActionSupport::UNSUPPORTED, $result->support);
       self::assertSame(ActionEvaluation::INDETERMINATE, $result->evaluation);
       self::assertSame(RuleTicketActionAnalyzer::REASON_INVALID_CONFIGURATION, $result->reason);
       self::assertFalse($result->configuredValuePresentationSafe);
       self::assertNull($result->configuredValue);
   }

   public function testInvalidExpectedIntegerIsIndeterminate(): void {
       $result = $this->analyzer->analyze(
           $this->action('assign', 'urgency', 'not-an-integer'),
           $this->context('urgency', 4)
       );

       self::assertSame(ActionSupport::SUPPORTED, $result->support);
       self::assertSame(ActionEvaluation::INDETERMINATE, $result->evaluation);
       self::assertSame(RuleTicketActionAnalyzer::REASON_INVALID_CONFIGURED_VALUE, $result->reason);
   }

    /** @dataProvider indeterminateByDesignActions */
   public function testKnownDynamicSemanticsAreIndeterminateByDesign(
        string $actionType,
        string $field,
        string $reason
    ): void {
       $result = $this->analyzer->analyze(
           $this->action($actionType, $field, '1'),
           new TicketContext([])
       );

       self::assertSame(ActionSupport::INDETERMINATE_BY_DESIGN, $result->support);
       self::assertSame(ActionEvaluation::INDETERMINATE, $result->evaluation);
       self::assertSame($reason, $result->reason);
   }

    /** @return iterable<string, array{string, string, string}> */
   public static function indeterminateByDesignActions(): iterable {
       yield 'compute' => ['compute', 'priority', RuleTicketActionAnalyzer::REASON_PRIORITY];
       yield 'from user' => ['fromuser', 'locations_id', RuleTicketActionAnalyzer::REASON_DYNAMIC_INPUT];
       yield 'regex' => ['regex_result', '_affect_itilcategory_by_code', RuleTicketActionAnalyzer::REASON_REGEX];
       yield 'lookup' => ['affectbyip', 'affectobject', RuleTicketActionAnalyzer::REASON_LOOKUP];
       yield 'validation' => ['add_validation', 'users_id_validate', RuleTicketActionAnalyzer::REASON_VALIDATION];
       yield 'template' => ['append', 'task_template', RuleTicketActionAnalyzer::REASON_TEMPLATE];
       yield 'control flow' => ['assign', '_stop_rules_processing', RuleTicketActionAnalyzer::REASON_TRANSIENT];
   }

   public function testUnknownAndSpecialRelationActionsAreUnsupported(): void {
       $unknown = $this->analyzer->analyze(
           $this->action('plugin_action', 'plugin_field', 'secret'),
           new TicketContext([])
       );
       $project = $this->analyzer->analyze(
           $this->action('assign', 'assign_project', '42'),
           new TicketContext([])
       );

       self::assertSame(ActionSupport::UNSUPPORTED, $unknown->support);
       self::assertSame(ActionEvaluation::INDETERMINATE, $unknown->evaluation);
       self::assertNull($unknown->configuredValue);
       self::assertSame(ActionSupport::UNSUPPORTED, $project->support);
       self::assertSame(RuleTicketActionAnalyzer::REASON_SPECIAL_RELATION, $project->reason);
   }

   private function action(string $type, string $field, mixed $value): ConfiguredAction {
       return new ConfiguredAction(1, 2, $type, $field, $value, 0);
   }

   private function context(string $field, mixed $value): TicketContext {
       return new TicketContext([
           $field => ContextValue::available($value, 'test', true),
       ]);
   }
}
