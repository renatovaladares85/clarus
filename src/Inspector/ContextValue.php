<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class ContextValue
{
   private function __construct(
        public readonly ContextState $state,
        public readonly mixed $value,
        public readonly string $source,
        public readonly ?string $reason,
        public readonly bool $presentationSafe
    ) {
   }

   public static function available(mixed $value, string $source, bool $presentationSafe = false): self {
       return new self(ContextState::AVAILABLE, $value, $source, null, $presentationSafe);
   }

   public static function indeterminate(string $reason): self {
       return new self(ContextState::INDETERMINATE, null, 'unavailable', $reason, false);
   }
}
