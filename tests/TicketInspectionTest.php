<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use GlpiPlugin\Clarus\ClarusConfig;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use GlpiPlugin\Clarus\Inspector\InspectionPresenter;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use GlpiPlugin\Clarus\TicketInspection;
use PHPUnit\Framework\TestCase;

final class TicketInspectionTest extends TestCase
{
   public function testGlobalBudgetIsConsumedFromOnaddToOnupdate(): void {
      $calls = [];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options) use (&$calls): InspectionResult {
         $calls[] = [$condition, $options->ruleLimit];

         return $this->result($ticket, $condition, $options, $condition === \RuleTicket::ONADD ? 2 : 4);
      });

      $results = $inspection->inspect($this->ticket(), $this->settings(3));

      self::assertSame([[\RuleTicket::ONADD, 3], [\RuleTicket::ONUPDATE, 1]], $calls);
      self::assertSame(3, array_sum(array_map(static fn (InspectionResult $result): int => $result->evaluatedCount, $results)));
      self::assertTrue($results[1]->truncated);

      $view = (new InspectionPresenter())->present(
         $results,
         $this->settings(3),
         12,
         '/refresh',
         'token',
         true
      );
      self::assertSame(6, $view['candidateCount']);
      self::assertSame(3, $view['evaluatedCount']);
      self::assertTrue($view['truncated']);
   }

   public function testExhaustedOnaddBudgetStillCountsUnevaluatedOnupdateCandidates(): void {
      $calls = [];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options) use (&$calls): InspectionResult {
         $calls[] = [$condition, $options->ruleLimit];

         return $this->result($ticket, $condition, $options, $condition === \RuleTicket::ONADD ? 3 : 4);
      });

      $results = $inspection->inspect($this->ticket(), $this->settings(3));

      self::assertSame([[\RuleTicket::ONADD, 3], [\RuleTicket::ONUPDATE, 0]], $calls);
      self::assertSame(3, $results[0]->evaluatedCount);
      self::assertSame(0, $results[1]->evaluatedCount);
      self::assertTrue($results[1]->truncated);
   }

   public function testGlobalBudgetDoesNotTruncateCandidatesBelowTheLimit(): void {
      $calls = [];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options) use (&$calls): InspectionResult {
         $calls[] = [$condition, $options->ruleLimit];

         return $this->result($ticket, $condition, $options, $condition === \RuleTicket::ONADD ? 400 : 500);
      });

      $results = $inspection->inspect($this->ticket(), $this->settings(1000));

      self::assertSame([[\RuleTicket::ONADD, 1000], [\RuleTicket::ONUPDATE, 600]], $calls);
      self::assertSame(900, array_sum(array_map(static fn (InspectionResult $result): int => $result->evaluatedCount, $results)));
      self::assertFalse($results[0]->truncated);
      self::assertFalse($results[1]->truncated);
   }

   public function testGlobalBudgetCapsBothConditionsAtOneThousandCandidates(): void {
      $calls = [];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options) use (&$calls): InspectionResult {
         $calls[] = [$condition, $options->ruleLimit];

         return $this->result($ticket, $condition, $options, 700);
      });

      $results = $inspection->inspect($this->ticket(), $this->settings(1000));

      self::assertSame([[\RuleTicket::ONADD, 1000], [\RuleTicket::ONUPDATE, 300]], $calls);
      self::assertSame(1400, array_sum(array_map(static fn (InspectionResult $result): int => $result->candidateCount, $results)));
      self::assertSame(1000, array_sum(array_map(static fn (InspectionResult $result): int => $result->evaluatedCount, $results)));
      self::assertTrue($results[1]->truncated);
   }

   /** @dataProvider singleConditionProvider */
   public function testSingleEnabledConditionReceivesTheCompleteGlobalBudget(
      bool $includeOnadd,
      bool $includeOnupdate,
      int $expectedCondition
   ): void {
      $calls = [];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options) use (&$calls): InspectionResult {
         $calls[] = [$condition, $options->ruleLimit];

         return $this->result($ticket, $condition, $options, 4);
      });
      $settings = $this->settings(3, $includeOnadd, $includeOnupdate);

      $results = $inspection->inspect($this->ticket(), $settings);

      self::assertSame([[$expectedCondition, 3]], $calls);
      self::assertSame(3, $results[0]->evaluatedCount);
      self::assertTrue($results[0]->truncated);
   }

   /** @return iterable<string, array{bool, bool, int}> */
   public static function singleConditionProvider(): iterable {
      yield 'ONADD only' => [true, false, \RuleTicket::ONADD];
      yield 'ONUPDATE only' => [false, true, \RuleTicket::ONUPDATE];
   }

   /** @return array<string, bool|int|string|list<array{field: string, direction: string}>> */
   private function settings(int $limit, bool $includeOnadd = true, bool $includeOnupdate = true): array {
      $settings = ClarusConfig::defaults();
      $settings[ClarusConfig::RULE_LIMIT] = $limit;
      $settings[ClarusConfig::INCLUDE_ONADD] = $includeOnadd;
      $settings[ClarusConfig::INCLUDE_ONUPDATE] = $includeOnupdate;

      return $settings;
   }

   private function ticket(): \Ticket {
      $ticket = new \Ticket();
      $ticket->fields = ['id' => 12];

      return $ticket;
   }

   private function result(\Ticket $ticket, int $condition, InspectionOptions $options, int $candidateCount): InspectionResult {
      $evaluatedCount = min($candidateCount, $options->ruleLimit);
      $rules = [];
      for ($index = 1; $index <= $evaluatedCount; ++$index) {
         $rules[] = new RuleInspection(
            $condition * 1000 + $index,
            'Rule ' . $index,
            $condition,
            0,
            false,
            $index,
            'AND',
            [],
            Evaluation::MATCH
         );
      }

      return new InspectionResult(
         $ticket->getID(),
         $condition,
         $options->ruleLimit,
         $candidateCount,
         $evaluatedCount,
         $candidateCount > $evaluatedCount,
         $rules
      );
   }
}
