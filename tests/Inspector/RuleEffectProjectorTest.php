<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ConfiguredAction;
use GlpiPlugin\Clarus\Inspector\ContextState;
use GlpiPlugin\Clarus\Inspector\ContextValue;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\ProjectionStatus;
use GlpiPlugin\Clarus\Inspector\RuleEffectProjector;
use GlpiPlugin\Clarus\Inspector\TicketContext;
use PHPUnit\Framework\TestCase;

final class RuleEffectProjectorTest extends TestCase
{
   private RuleEffectProjector $projector;

   protected function setUp(): void {
       $this->projector = new RuleEffectProjector();
   }

   public function testMatchedScalarAssignmentBuildsAnImmutableNextContext(): void {
       $input = $this->context(['urgency' => 3]);
       $result = $this->projector->project(
           Evaluation::MATCH,
           [$this->action('assign', 'urgency', '5')],
           $input
       );

       self::assertSame(3, $input->get('urgency')->value);
       self::assertSame(5, $result->outputContext->get('urgency')->value);
       self::assertSame(ProjectionStatus::APPLIED, $result->effects[0]->status);
       self::assertSame(3, $result->effects[0]->previousValue);
       self::assertSame(5, $result->effects[0]->nextValue);
   }

   public function testNoMatchLeavesTheContextUntouched(): void {
       $input = $this->context(['impact' => 3]);
       $result = $this->projector->project(
           Evaluation::NO_MATCH,
           [$this->action('assign', 'impact', '5')],
           $input
       );

       self::assertSame(3, $result->outputContext->get('impact')->value);
       self::assertSame(ProjectionStatus::NOT_APPLIED, $result->effects[0]->status);
   }

   public function testIndeterminateRuleTaintsOnlyItsKnownTarget(): void {
       $input = $this->context(['urgency' => 3, 'impact' => 3]);
       $result = $this->projector->project(
           Evaluation::INDETERMINATE,
           [$this->action('assign', 'urgency', '5')],
           $input
       );

       self::assertSame(ContextState::INDETERMINATE, $result->outputContext->get('urgency')->state);
       self::assertSame(3, $result->outputContext->get('impact')->value);
       self::assertSame(ProjectionStatus::INDETERMINATE, $result->effects[0]->status);
       self::assertSame(RuleEffectProjector::REASON_RULE_RESULT_INDETERMINATE, $result->effects[0]->reason);
   }

   public function testUnsupportedKnownTargetIsNotApproximated(): void {
       $input = $this->context(['_users_id_assign' => [3]]);
       $result = $this->projector->project(
           Evaluation::MATCH,
           [$this->action('append', '_users_id_assign', '7')],
           $input
       );

       self::assertSame(ContextState::INDETERMINATE, $result->outputContext->get('_users_id_assign')->state);
       self::assertSame(ProjectionStatus::UNSUPPORTED, $result->effects[0]->status);
       self::assertSame(RuleEffectProjector::REASON_UNSUPPORTED_ACTION, $result->effects[0]->reason);
   }

   public function testMatchedDeleteProjectsExactNull(): void {
       $input = $this->context(['time_to_resolve' => '2026-09-11 12:00:00']);
       $result = $this->projector->project(
           Evaluation::MATCH,
           [$this->action('delete', 'time_to_resolve', '1')],
           $input
       );

       self::assertSame(ContextState::AVAILABLE, $result->outputContext->get('time_to_resolve')->state);
       self::assertNull($result->outputContext->get('time_to_resolve')->value);
       self::assertSame(ProjectionStatus::APPLIED, $result->effects[0]->status);
   }

   /** @param array<string, mixed> $values */
   private function context(array $values): TicketContext {
       return new TicketContext(array_map(
           static fn (mixed $value): ContextValue => ContextValue::available($value, 'test', true),
           $values
       ));
   }

   private function action(string $type, string $field, mixed $value): ConfiguredAction {
       return new ConfiguredAction(1, 2, $type, $field, $value, 0);
   }
}
