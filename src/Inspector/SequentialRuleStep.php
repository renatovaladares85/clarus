<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Internal trace of an immutable simulated RuleTicket step.
 *
 * It is intentionally not part of the Ticket tab view model in this phase.
 */
final class SequentialRuleStep
{
   /** @param list<ProjectedRuleEffect> $effects */
   public function __construct(
       public readonly int $processingIndex,
       public readonly TicketContext $inputContext,
       public readonly TicketContext $outputContext,
       public readonly array $effects
   ) {
      if ($processingIndex < 0) {
          throw new \InvalidArgumentException('Sequential processing index must not be negative.');
      }
   }
}
