<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class EvaluationReducer
{
    /** @param list<Evaluation> $evaluations */
   public static function reduce(string $matchingMode, array $evaluations): Evaluation {
      if ($matchingMode === 'AND') {
         if (in_array(Evaluation::NO_MATCH, $evaluations, true)) {
            return Evaluation::NO_MATCH;
         }
         if (in_array(Evaluation::INDETERMINATE, $evaluations, true)) {
             return Evaluation::INDETERMINATE;
         }

          return Evaluation::MATCH;
      }

      if ($matchingMode === 'OR') {
         if (in_array(Evaluation::MATCH, $evaluations, true)) {
             return Evaluation::MATCH;
         }
         if (in_array(Evaluation::INDETERMINATE, $evaluations, true)) {
             return Evaluation::INDETERMINATE;
         }

          return Evaluation::NO_MATCH;
      }

       throw new \InvalidArgumentException('Unsupported RuleTicket matching mode.');
   }
}
