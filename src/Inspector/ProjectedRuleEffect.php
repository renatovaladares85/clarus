<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Immutable, internal-only record of one action in a simulated rule step. */
final class ProjectedRuleEffect
{
   public function __construct(
       public readonly int $actionId,
       public readonly string $actionType,
       public readonly string $field,
       public readonly ProjectionStatus $status,
       public readonly ?string $reason = null,
       public readonly bool $hasPreviousValue = false,
       public readonly mixed $previousValue = null,
       public readonly bool $hasNextValue = false,
       public readonly mixed $nextValue = null
   ) {
      if (in_array($status, [ProjectionStatus::INDETERMINATE, ProjectionStatus::UNSUPPORTED], true)
          && ($reason === null || $reason === '')) {
          throw new \InvalidArgumentException('Indeterminate and unsupported projected effects require a reason.');
      }
      if (!in_array($status, [ProjectionStatus::INDETERMINATE, ProjectionStatus::UNSUPPORTED], true)
          && $reason !== null) {
          throw new \InvalidArgumentException('Only indeterminate and unsupported projected effects may have a reason.');
      }
   }
}
