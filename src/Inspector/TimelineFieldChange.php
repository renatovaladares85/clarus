<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Immutable, raw persisted field evidence from GLPI history. */
final class TimelineFieldChange
{
   public function __construct(
       public readonly int $id,
       public readonly string $field,
       public readonly mixed $before,
       public readonly mixed $after,
       public readonly string $occurredAt
   ) {
   }
}
