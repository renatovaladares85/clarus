<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class ActionInspection
{
   public function __construct(
        public readonly int $actionId,
        public readonly string $actionType,
        public readonly string $field,
        public readonly ActionSupport $support,
        public readonly ActionEvaluation $evaluation,
        public readonly ?string $reason = null,
        public readonly bool $configuredValuePresentationSafe = false,
        public readonly mixed $configuredValue = null,
        public readonly bool $currentValuePresentationSafe = false,
        public readonly mixed $currentValue = null
    ) {
      if ($support !== ActionSupport::SUPPORTED && $evaluation !== ActionEvaluation::INDETERMINATE) {
          throw new \InvalidArgumentException('Unsupported action semantics must be indeterminate.');
      }
      if ($evaluation === ActionEvaluation::INDETERMINATE && ($reason === null || $reason === '')) {
          throw new \InvalidArgumentException('Indeterminate action evaluation requires a reason.');
      }
      if ($evaluation !== ActionEvaluation::INDETERMINATE && $reason !== null) {
          throw new \InvalidArgumentException('Determinate action evaluation cannot have a reason.');
      }
      if (!$configuredValuePresentationSafe && $configuredValue !== null) {
          throw new \InvalidArgumentException('Unsafe configured values cannot be exposed.');
      }
      if (!$currentValuePresentationSafe && $currentValue !== null) {
          throw new \InvalidArgumentException('Unsafe current values cannot be exposed.');
      }
   }
}
