<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use GlpiPlugin\Clarus\Inspector\CriterionInspection;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use GlpiPlugin\Clarus\Profile;
use GlpiPlugin\Clarus\TicketInspection;
use GlpiPlugin\Clarus\TicketTab;
use PHPUnit\Framework\TestCase;

final class TicketTabTest extends TestCase
{
   protected function tearDown(): void {
      \Session::$hasRight = false;
      \Session::$rights = [];
      parent::tearDown();
   }

   public function testTabIsHiddenAndContentIsDeniedWithoutTheClarusRight(): void {
      $ticket = $this->ticket(true);
      $tab = new TicketTab();

      self::assertSame('', $tab->getTabNameForItem($ticket));
      self::assertFalse(TicketTab::displayTabContentForItem($ticket));
   }

   public function testTabIsHiddenAndContentIsDeniedWithoutNativeTicketVisibility(): void {
      \Session::$hasRight = true;
      $ticket = $this->ticket(false);
      $tab = new TicketTab();

      self::assertSame('', $tab->getTabNameForItem($ticket));
      self::assertFalse(TicketTab::displayTabContentForItem($ticket));
   }

   public function testAuthorizedPersistedTicketExposesTheNativeTab(): void {
      \Session::$hasRight = true;
      $tab = new TicketTab();

      self::assertSame('Rule inspection', $tab->getTabNameForItem($this->ticket(true)));
   }

   public function testNewTicketTabRenderRechecksSensitiveRightAfterRevocation(): void {
      \Session::$rights = [
         Profile::RIGHT_INSPECT => true,
         Profile::RIGHT_SHOW_SENSITIVE => true,
      ];
      $inspection = new TicketInspection(function (\Ticket $ticket, int $condition, InspectionOptions $options): InspectionResult {
         if ($condition === \RuleTicket::ONUPDATE) {
            return new InspectionResult($ticket->getID(), $condition, $options->ruleLimit, 0, 0, false, []);
         }

         $criterion = new CriterionInspection(
            'content',
            2,
            'secret-pattern',
            Evaluation::MATCH,
            null,
            true,
            'secret-observed-value',
            true
         );
         $rule = new RuleInspection(7, 'Rule', $condition, 0, false, 1, 'AND', [$criterion], Evaluation::MATCH);

         return new InspectionResult($ticket->getID(), $condition, $options->ruleLimit, 1, 1, false, [$rule]);
      });
      $ticket = $this->ticket(true);

      $authorized = TicketTab::renderInspection($ticket, true, $inspection);
      \Session::$rights[Profile::RIGHT_SHOW_SENSITIVE] = false;
      $afterRevocation = TicketTab::renderInspection($ticket, true, $inspection);

      self::assertStringContainsString('secret-pattern', $authorized);
      self::assertStringContainsString('secret-observed-value', $authorized);
      self::assertStringNotContainsString('secret-pattern', $afterRevocation);
      self::assertStringNotContainsString('secret-observed-value', $afterRevocation);
   }

   private function ticket(bool $viewable): \Ticket {
      $ticket = new \Ticket();
      $ticket->fields = ['id' => 12];
      $ticket->viewable = $viewable;

      return $ticket;
   }
}
