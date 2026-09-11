<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\OverwriteClassification;
use GlpiPlugin\Clarus\Inspector\ProjectedRuleEffect;
use GlpiPlugin\Clarus\Inspector\ProjectionStatus;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use GlpiPlugin\Clarus\Inspector\SequentialOverwriteAnalyzer;
use GlpiPlugin\Clarus\Inspector\SequentialRuleStep;
use GlpiPlugin\Clarus\Inspector\TicketContext;
use PHPUnit\Framework\TestCase;

final class SequentialOverwriteAnalyzerTest extends TestCase
{
   private SequentialOverwriteAnalyzer $analyzer;

   protected function setUp(): void {
       $this->analyzer = new SequentialOverwriteAnalyzer();
   }

   public function testClassifiesDifferentDeterministicValuesAsConfirmed(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::MATCH, [$this->applied('urgency', 1, 2)]),
       ]);

       self::assertCount(1, $overwrites);
       $overwrite = $overwrites[0];
       self::assertSame(OverwriteClassification::CONFIRMED, $overwrite->classification);
       self::assertSame('urgency', $overwrite->field);
       self::assertSame(11, $overwrite->previousRuleId);
       self::assertSame(12, $overwrite->laterRuleId);
       self::assertSame(0, $overwrite->previousProcessingIndex);
       self::assertSame(1, $overwrite->laterProcessingIndex);
       self::assertSame(3, $overwrite->previousValue);
       self::assertSame(1, $overwrite->intermediateValue);
       self::assertSame(2, $overwrite->finalValue);
   }

   public function testIndeterminateOrUnsupportedLaterEffectIsOnlyPossible(): void {
       $forIndeterminate = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::INDETERMINATE, [$this->unknown('urgency', ProjectionStatus::INDETERMINATE)]),
       ]);
       $forUnsupported = $this->analyzer->analyze([
           $this->rule(21, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(22, 1, Evaluation::MATCH, [$this->unknown('urgency', ProjectionStatus::UNSUPPORTED)]),
       ]);

       self::assertSame(OverwriteClassification::POSSIBLE, $forIndeterminate[0]->classification);
       self::assertSame('rule_result_indeterminate', $forIndeterminate[0]->reason);
       self::assertSame(OverwriteClassification::POSSIBLE, $forUnsupported[0]->classification);
       self::assertSame('unsupported_action_semantics', $forUnsupported[0]->reason);
   }

   public function testNoMatchAndSameValueDoNotClassifyAnOverwrite(): void {
       $noMatch = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::NO_MATCH, [$this->notApplied('urgency')]),
       ]);
       $sameValue = $this->analyzer->analyze([
           $this->rule(21, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(22, 1, Evaluation::MATCH, [$this->applied('urgency', 1, 1)]),
       ]);

       self::assertSame([], $noMatch);
       self::assertSame([], $sameValue);
   }

   public function testAdditiveActionDoesNotClassifyAnOverwrite(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::MATCH, [$this->unknown('urgency', ProjectionStatus::UNSUPPORTED, 'append')]),
       ]);

       self::assertSame([], $overwrites);
   }

   public function testKeepsTheDeterministicRuleChainAndFinalValue(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::MATCH, [$this->applied('urgency', 1, 2)]),
           $this->rule(13, 2, Evaluation::MATCH, [$this->applied('urgency', 2, 3)]),
       ]);

       self::assertCount(2, $overwrites);
       self::assertSame([11, 12], array_column($overwrites, 'previousRuleId'));
       self::assertSame([12, 13], array_column($overwrites, 'laterRuleId'));
       self::assertSame(3, $overwrites[1]->finalValue);
   }

   public function testUsesEffectiveProcessingPositionInsteadOfRanking(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)], 90),
           $this->rule(12, 1, Evaluation::MATCH, [$this->applied('urgency', 1, 2)], 1),
       ]);

       self::assertCount(1, $overwrites);
       self::assertSame(0, $overwrites[0]->previousProcessingIndex);
       self::assertSame(1, $overwrites[0]->laterProcessingIndex);
   }

   public function testRelevantGapPreventsAConfirmedOverwrite(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)]),
           $this->rule(12, 1, Evaluation::INDETERMINATE, [$this->unknown('urgency', ProjectionStatus::INDETERMINATE)]),
           $this->rule(13, 2, Evaluation::MATCH, [$this->applied('urgency', 1, 3)]),
       ]);

       self::assertCount(2, $overwrites);
      foreach ($overwrites as $overwrite) {
          self::assertSame(OverwriteClassification::POSSIBLE, $overwrite->classification);
      }
   }

   public function testOnaddAndOnupdateNeverShareAProducer(): void {
       $overwrites = $this->analyzer->analyze([
           $this->rule(11, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 1)], 1, \RuleTicket::ONADD),
           $this->rule(12, 0, Evaluation::MATCH, [$this->applied('urgency', 3, 2)], 1, \RuleTicket::ONUPDATE),
       ]);

       self::assertSame([], $overwrites);
   }

   /** @param list<ProjectedRuleEffect> $effects */
   private function rule(
       int $id,
       int $processingIndex,
       Evaluation $evaluation,
       array $effects,
       int $ranking = 1,
       int $condition = \RuleTicket::ONADD
   ): RuleInspection {
       $context = new TicketContext([]);

       return new RuleInspection(
           $id,
           'Rule ' . $id,
           $condition,
           0,
           true,
           $ranking,
           'AND',
           [],
           $evaluation,
           [],
           [],
           new SequentialRuleStep($processingIndex, $context, $context, $effects)
       );
   }

   private function applied(string $field, mixed $previous, mixed $next): ProjectedRuleEffect {
       return new ProjectedRuleEffect(1, 'assign', $field, ProjectionStatus::APPLIED, null, true, $previous, true, $next);
   }

   private function notApplied(string $field): ProjectedRuleEffect {
       return new ProjectedRuleEffect(1, 'assign', $field, ProjectionStatus::NOT_APPLIED);
   }

   private function unknown(string $field, ProjectionStatus $status, string $actionType = 'assign'): ProjectedRuleEffect {
       return new ProjectedRuleEffect(
           1,
           $actionType,
           $field,
           $status,
           $status === ProjectionStatus::INDETERMINATE
               ? 'rule_result_indeterminate'
               : 'unsupported_action_semantics'
       );
   }
}
