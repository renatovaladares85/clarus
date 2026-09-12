<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Immutable internal diagnostic for two sequential rules that can overwrite a field.
 *
 * Values remain in the domain layer. The presenter deliberately does not expose them.
 */
final class RuleOverwrite
{
   public function __construct(
       public readonly string $field,
       public readonly int $previousRuleId,
       public readonly int $laterRuleId,
       public readonly int $previousProcessingIndex,
       public readonly int $laterProcessingIndex,
       public readonly OverwriteClassification $classification,
       public readonly ?string $reason,
       public readonly bool $hasPreviousValue,
       public readonly mixed $previousValue,
       public readonly mixed $intermediateValue,
       public readonly mixed $finalValue
   ) {
      if ($field === '' || $previousRuleId <= 0 || $laterRuleId <= 0
          || $previousProcessingIndex < 0 || $laterProcessingIndex <= $previousProcessingIndex) {
          throw new \InvalidArgumentException('Rule overwrite metadata must describe two ordered rules and a field.');
      }
      if ($classification === OverwriteClassification::CONFIRMED && $reason !== null) {
          throw new \InvalidArgumentException('Confirmed overwrites must not have an uncertainty reason.');
      }
      if ($classification === OverwriteClassification::POSSIBLE && ($reason === null || $reason === '')) {
          throw new \InvalidArgumentException('Possible overwrites require an uncertainty reason.');
      }
   }
}
