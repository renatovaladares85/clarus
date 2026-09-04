<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class CriterionInspection
{
   public function __construct(
        public readonly string $key,
        public readonly int $operator,
        public readonly string $pattern,
        public readonly Evaluation $evaluation,
        public readonly ?string $reason = null,
        public readonly bool $hasObservedValue = false,
        public readonly mixed $observedValue = null
    ) {
   }
}
