<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use GlpiPlugin\Clarus\TicketTab;
use PHPUnit\Framework\TestCase;

final class TicketTabTest extends TestCase
{
   protected function tearDown(): void {
      \Session::$hasRight = false;
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

   private function ticket(bool $viewable): \Ticket {
      $ticket = new \Ticket();
      $ticket->fields = ['id' => 12];
      $ticket->viewable = $viewable;

      return $ticket;
   }
}
