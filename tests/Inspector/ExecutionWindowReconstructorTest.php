<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ContextState;
use GlpiPlugin\Clarus\Inspector\ContextValue;
use GlpiPlugin\Clarus\Inspector\ExecutionWindowReconstructor;
use GlpiPlugin\Clarus\Inspector\TicketContext;
use GlpiPlugin\Clarus\Inspector\TicketTimeline;
use GlpiPlugin\Clarus\Inspector\TimelineFieldChange;
use PHPUnit\Framework\TestCase;

final class ExecutionWindowReconstructorTest extends TestCase
{
   public function testUsesOldestDurableBeforeValueAndNeverLeaksTheCurrentSnapshot(): void {
       $timeline = new TicketTimeline($this->currentContext(), [
           new TimelineFieldChange(10, 'urgency', '3', '4', '2026-09-12 10:00:00'),
           new TimelineFieldChange(11, 'urgency', '4', '5', '2026-09-12 11:00:00'),
           new TimelineFieldChange(12, 'impact', '2', '4', '2026-09-12 11:00:00'),
       ]);

       $window = (new ExecutionWindowReconstructor())->reconstruct($timeline, \RuleTicket::ONADD);

       self::assertSame('3', $window->inputContext->get('urgency')->value);
       self::assertSame('2', $window->inputContext->get('impact')->value);
       self::assertSame(ContextState::INDETERMINATE, $window->inputContext->get('name')->state);
       self::assertFalse($window->boundaryKnown);
       self::assertStringContainsString('not proof', implode(' ', $window->limitations));
   }

   public function testMissingHistoryFailsClosedForEveryCurrentValue(): void {
       $window = (new ExecutionWindowReconstructor())->reconstruct(
           new TicketTimeline($this->currentContext(), []),
           \RuleTicket::ONUPDATE
       );

       self::assertSame(ContextState::INDETERMINATE, $window->inputContext->get('urgency')->state);
       self::assertSame(ContextState::INDETERMINATE, $window->inputContext->get('name')->state);
       self::assertStringContainsString('No durable', implode(' ', $window->limitations));
   }

   public function testBuildsIndependentOnupdateCandidatesFromChronologicalHistory(): void {
       $timeline = new TicketTimeline($this->currentContext(), [
           new TimelineFieldChange(10, 'urgency', '3', '4', '2026-09-12 10:00:00'),
           new TimelineFieldChange(11, 'impact', '2', '3', '2026-09-12 10:00:00'),
           new TimelineFieldChange(12, 'urgency', '4', '5', '2026-09-12 11:00:00'),
       ]);

       $windows = (new ExecutionWindowReconstructor())->reconstructAll($timeline);

       self::assertCount(3, $windows);
       self::assertSame(\RuleTicket::ONADD, $windows[0]->condition);
       self::assertSame('3', $windows[0]->inputContext->get('urgency')->value);
       self::assertSame('2', $windows[0]->inputContext->get('impact')->value);
       self::assertSame(\RuleTicket::ONUPDATE, $windows[1]->condition);
       self::assertSame('3', $windows[1]->inputContext->get('urgency')->value);
       self::assertSame('2', $windows[1]->inputContext->get('impact')->value);
       self::assertCount(2, $windows[1]->evidence);
       self::assertSame('4', $windows[2]->inputContext->get('urgency')->value);
       self::assertSame('3', $windows[2]->inputContext->get('impact')->value);
       self::assertFalse($windows[1]->boundaryKnown);
       self::assertStringContainsString('possible ONUPDATE', implode(' ', $windows[1]->limitations));
   }

   private function currentContext(): TicketContext {
       return new TicketContext([
           'urgency' => ContextValue::available('5', 'ticket', true),
           'impact' => ContextValue::available('4', 'ticket', true),
           'name' => ContextValue::available('Current subject', 'ticket'),
       ]);
   }
}
