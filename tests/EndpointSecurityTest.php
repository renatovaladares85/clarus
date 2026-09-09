<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use PHPUnit\Framework\TestCase;

final class EndpointSecurityTest extends TestCase
{
   public function testRefreshEndpointEnforcesPostCsrfAndTicketAuthorization(): void {
       $source = file_get_contents(dirname(__DIR__) . '/ajax/inspection.php');

       self::assertIsString($source);
       self::assertStringContainsString("REQUEST_METHOD", $source);
       self::assertStringContainsString('$input = $_POST', $source);
       self::assertStringContainsString('Session::checkCSRF($input)', $source);
       self::assertStringContainsString('Profile::RIGHT_INSPECT', $source);
       self::assertStringContainsString('Authorization::canInspectTicket($ticket)', $source);
       self::assertStringContainsString('FILTER_VALIDATE_INT', $source);
   }

   public function testConfigurationPageEnforcesNativeUpdateRightAndCsrf(): void {
       $source = file_get_contents(dirname(__DIR__) . '/front/config.php');

       self::assertIsString($source);
       self::assertStringContainsString("Session::checkRight('config', UPDATE)", $source);
       self::assertStringContainsString('$input = $_POST', $source);
       self::assertStringContainsString('Session::checkCSRF($input)', $source);
       self::assertStringContainsString('ClarusConfig::update($input)', $source);
   }
}
