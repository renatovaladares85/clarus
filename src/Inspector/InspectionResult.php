<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class InspectionResult
{
    /**
     * @param list<RuleInspection> $rules
     * @param list<string> $limitations
     */
   public function __construct(
        public readonly int $ticketId,
        public readonly int $condition,
        public readonly int $configuredLimit,
        public readonly int $candidateCount,
        public readonly int $evaluatedCount,
        public readonly bool $truncated,
        public readonly array $rules,
        public readonly array $limitations = []
    ) {
      if ($configuredLimit < 1 || $candidateCount < 0 || $evaluatedCount < 0) {
          throw new \InvalidArgumentException('Inspection result counts and limit must be valid.');
      }
      if ($evaluatedCount > $candidateCount || $evaluatedCount !== count($rules)) {
          throw new \InvalidArgumentException('Inspection result counts must match the evaluated rules.');
      }
      if ($truncated !== ($candidateCount > $evaluatedCount)) {
          throw new \InvalidArgumentException('Inspection truncation flag is inconsistent with its counts.');
      }
   }
}
