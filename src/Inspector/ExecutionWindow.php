<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * A defensible replay starting point. It is intentionally separate from the
 * current Ticket snapshot and does not assert that a native execution ran.
 */
final class ExecutionWindow
{
   /** @param list<string> $limitations */
   public function __construct(
       public readonly int $condition,
       public readonly TicketContext $inputContext,
       public readonly bool $boundaryKnown,
       public readonly array $limitations = []
   ) {
   }
}
