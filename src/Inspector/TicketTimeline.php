<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Current state plus the subset of GLPI history whose before/after values can
 * be read without parsing localized history labels.
 */
final class TicketTimeline
{
   /** @param list<TimelineFieldChange> $changes */
   public function __construct(
       public readonly TicketContext $currentContext,
       public readonly array $changes
   ) {
   }
}
