<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class InspectionOptions
{
   public const DEFAULT_LIMIT = 1000;

   public function __construct(
        public readonly int $ruleLimit = self::DEFAULT_LIMIT,
        public readonly bool $includeActions = false
    ) {
      if ($ruleLimit < 1) {
          throw new \InvalidArgumentException('Rule inspection limit must be a positive integer.');
      }
   }
}
