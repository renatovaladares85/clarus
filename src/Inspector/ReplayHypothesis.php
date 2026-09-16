<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** One bounded, non-confirmatory interpretation of a retained ONUPDATE group. */
final class ReplayHypothesis
{
   /** @param list<string> $onlyCriteria */
   public function __construct(
       public readonly TicketContext $inputContext,
       public readonly array $onlyCriteria
   ) {
   }
}
