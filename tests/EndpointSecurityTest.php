<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests;

use PHPUnit\Framework\TestCase;

final class EndpointSecurityTest extends TestCase
{
   public function testRefreshEndpointReliesOnNativeCsrfAndEnforcesTicketAuthorization(): void {
       $source = file_get_contents(dirname(__DIR__) . '/ajax/inspection.php');

       self::assertIsString($source);
       self::assertStringContainsString("include dirname(__DIR__, 3) . '/inc/includes.php';", $source);
       self::assertStringContainsString("REQUEST_METHOD", $source);
       self::assertStringContainsString('$input = $_POST', $source);
       self::assertStringNotContainsString('Session::checkCSRF(', $source);
       self::assertStringContainsString('Profile::RIGHT_INSPECT', $source);
       self::assertStringContainsString('Authorization::canInspectTicket($ticket)', $source);
       self::assertStringContainsString('FILTER_VALIDATE_INT', $source);
   }

   public function testConfigurationPageReliesOnNativeCsrfAndEnforcesUpdateRight(): void {
       $source = file_get_contents(dirname(__DIR__) . '/front/config.php');

       self::assertIsString($source);
       self::assertStringContainsString("include dirname(__DIR__, 3) . '/inc/includes.php';", $source);
       self::assertStringContainsString("Session::checkRight('config', UPDATE)", $source);
       self::assertStringContainsString('$input = $_POST', $source);
       self::assertStringNotContainsString('Session::checkCSRF(', $source);
       self::assertStringContainsString('ClarusConfig::update($input)', $source);
       self::assertLessThan(
           strpos($source, "if ((\$_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')"),
           strpos($source, "Session::checkRight('config', UPDATE)")
       );
       self::assertLessThan(
           strpos($source, 'ClarusConfig::update($input)'),
           strpos($source, '$input = $_POST')
       );
   }

   public function testRefreshJavascriptSendsTheNativeGlpiCsrfHeader(): void {
       $source = file_get_contents(dirname(__DIR__) . '/js/inspection.js');

       self::assertIsString($source);
       self::assertStringContainsString('const formData = new FormData(form);', $source);
       self::assertStringContainsString("formData.get('_glpi_csrf_token')", $source);
       self::assertStringContainsString("'X-Glpi-Csrf-Token': typeof csrfToken === 'string' ? csrfToken : ''", $source);
       self::assertStringContainsString('body: formData,', $source);
       self::assertStringContainsString("credentials: 'same-origin'", $source);
   }
}
