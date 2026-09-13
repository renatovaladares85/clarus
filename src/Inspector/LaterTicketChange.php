<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * A durable Ticket-history transition kept distinct from same-execution rule
 * replay. It never asserts that a business rule caused the change.
 */
final class LaterTicketChange
{
   public function __construct(
       public readonly TimelineFieldChange $change,
       public readonly LaterChangeClassification $classification = LaterChangeClassification::NOT_ATTRIBUTABLE_TO_RULE
   ) {
   }
}
