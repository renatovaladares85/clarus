<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleEffectProjection
{
   /** @param list<ProjectedRuleEffect> $effects */
   public function __construct(
       public readonly TicketContext $outputContext,
       public readonly array $effects
   ) {
   }
}
