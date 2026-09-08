<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use GlpiPlugin\Clarus\Authorization;
use GlpiPlugin\Clarus\Profile;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
   protected function tearDown(): void {
       \Session::$hasRight = false;
       parent::tearDown();
   }

   public function testProfileDefinesTheSingleReadRight(): void {
       self::assertSame('plugin_clarus_inspect', Profile::RIGHT_INSPECT);
       self::assertSame([
           [
               'itemtype' => Profile::class,
               'label'    => 'Rule inspection',
               'field'    => Profile::RIGHT_INSPECT,
               'rights'   => [READ => 'Read'],
           ],
       ], Profile::getAllRights());
   }

   public function testAuthorizationDeniesWhenTheClarusRightIsMissing(): void {
       $ticket = new \Ticket();
       $ticket->viewable = true;

       self::assertFalse(Authorization::canInspectTicket($ticket));
   }

   public function testAuthorizationRequiresNativeTicketVisibility(): void {
       \Session::$hasRight = true;
       $ticket = new \Ticket();

       self::assertFalse(Authorization::canInspectTicket($ticket));

       $ticket->viewable = true;
       self::assertTrue(Authorization::canInspectTicket($ticket));
   }

   public function testRightRegistrationIsIdempotentAndDoesNotGrantRead(): void {
       \ProfileRight::$possibleRights = [];

       self::assertTrue(Profile::registerRights());
       self::assertSame('', \ProfileRight::getAllPossibleRights()[Profile::RIGHT_INSPECT]);
       self::assertTrue(Profile::registerRights());
       self::assertCount(1, \ProfileRight::$possibleRights);
       self::assertTrue(Profile::unregisterRights());
       self::assertArrayNotHasKey(Profile::RIGHT_INSPECT, \ProfileRight::$possibleRights);
   }
}
