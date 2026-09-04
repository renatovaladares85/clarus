<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ContextState;
use GlpiPlugin\Clarus\Inspector\ContextValue;
use GlpiPlugin\Clarus\Inspector\TicketContext;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class TicketContextTest extends TestCase
{
   public function testAvailableNullIsDifferentFromUnavailableValue(): void {
       $context = new TicketContext([
           'available_null' => ContextValue::available(null, 'ticket'),
       ]);

       self::assertSame(ContextState::AVAILABLE, $context->get('available_null')->state);
       self::assertArrayHasKey('available_null', $context->availableInput());
       self::assertNull($context->availableInput()['available_null']);
       self::assertSame(ContextState::INDETERMINATE, $context->get('missing')->state);
       self::assertArrayNotHasKey('missing', $context->availableInput());
   }

   public function testContextValuesAreImmutable(): void {
       $value = ContextValue::available(4, 'ticket', true);

       self::assertTrue((new ReflectionProperty($value, 'value'))->isReadOnly());
       self::assertSame(4, $value->value);
   }
}
