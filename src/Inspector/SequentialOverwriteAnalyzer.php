<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Classifies only immutable RuleEffectProjector output; it never evaluates or executes rules.
 */
final class SequentialOverwriteAnalyzer
{
   /** @var list<string> */
   private const ADDITIVE_OR_COMPOSITIONAL_ACTION_TYPES = ['append', 'add', 'remove'];

   /**
    * @param list<RuleInspection> $rules Native candidate order for one condition.
    * @return list<RuleOverwrite>
    */
   public function analyze(array $rules): array {
       $overwrites = [];
       /** @var array<string, array{ruleId: int, processingIndex: int, hasPreviousValue: bool, previousValue: mixed, intermediateValue: mixed}> $producers */
       $producers = [];
       /** @var array<string, array{ruleId: int, processingIndex: int, reason: string}> $uncertainProducers */
       $uncertainProducers = [];
       $condition = null;
       $lastProcessingIndex = -1;

      foreach ($rules as $rule) {
         if ($condition !== null && $rule->condition !== $condition) {
             // Conditions begin independent simulations and must not form a causal chain.
             $producers = [];
             $uncertainProducers = [];
             $lastProcessingIndex = -1;
         }
         $condition = $rule->condition;
         $step = $rule->sequentialStep;
         if ($step === null) {
             continue;
         }
         if ($step->processingIndex <= $lastProcessingIndex) {
             throw new \InvalidArgumentException('Sequential overwrite analysis requires native processing order.');
         }
         $lastProcessingIndex = $step->processingIndex;

         foreach ($step->effects as $effect) {
            if ($effect->status === ProjectionStatus::NOT_APPLIED
                || in_array($effect->actionType, self::ADDITIVE_OR_COMPOSITIONAL_ACTION_TYPES, true)) {
                continue;
            }

            $field = $effect->field;
            if ($effect->status === ProjectionStatus::APPLIED && $effect->hasNextValue) {
               if (isset($uncertainProducers[$field])) {
                   $uncertain = $uncertainProducers[$field];
                  if ($uncertain['ruleId'] !== $rule->id) {
                        $overwrites[] = $this->possible(
                            $field,
                            $uncertain['ruleId'],
                            $rule->id,
                            $uncertain['processingIndex'],
                            $step->processingIndex,
                            $uncertain['reason'],
                            false,
                            null,
                            null,
                            $effect->nextValue
                        );
                  }
                   unset($uncertainProducers[$field]);
               } else if (isset($producers[$field])) {
                   $producer = $producers[$field];
                  if ($producer['ruleId'] !== $rule->id && $producer['intermediateValue'] !== $effect->nextValue) {
                        $overwrites[] = new RuleOverwrite(
                            $field,
                            $producer['ruleId'],
                            $rule->id,
                            $producer['processingIndex'],
                            $step->processingIndex,
                            OverwriteClassification::CONFIRMED,
                            null,
                            $producer['hasPreviousValue'],
                            $producer['previousValue'],
                            $producer['intermediateValue'],
                            $effect->nextValue
                        );
                  }
               }

               if (!isset($producers[$field]) || $producers[$field]['ruleId'] === $rule->id
                   || $producers[$field]['intermediateValue'] !== $effect->nextValue) {
                    $producers[$field] = [
                        'ruleId' => $rule->id,
                        'processingIndex' => $step->processingIndex,
                        'hasPreviousValue' => $effect->hasPreviousValue,
                        'previousValue' => $effect->previousValue,
                        'intermediateValue' => $effect->nextValue,
                    ];
               }
                continue;
            }

            if (!in_array($effect->status, [ProjectionStatus::INDETERMINATE, ProjectionStatus::UNSUPPORTED], true)) {
                continue;
            }

            $reason = $effect->reason ?? RuleEffectProjector::REASON_UNSUPPORTED_ACTION;
            if (isset($producers[$field]) && $producers[$field]['ruleId'] !== $rule->id) {
                $producer = $producers[$field];
                $overwrites[] = $this->possible(
                    $field,
                    $producer['ruleId'],
                    $rule->id,
                    $producer['processingIndex'],
                    $step->processingIndex,
                    $reason,
                    $producer['hasPreviousValue'],
                    $producer['previousValue'],
                    $producer['intermediateValue'],
                    null
                );
                unset($producers[$field]);
            }
            $uncertainProducers[$field] = [
                'ruleId' => $rule->id,
                'processingIndex' => $step->processingIndex,
                'reason' => $reason,
            ];
         }
      }

       return $overwrites;
   }

   private function possible(
       string $field,
       int $previousRuleId,
       int $laterRuleId,
       int $previousProcessingIndex,
       int $laterProcessingIndex,
       string $reason,
       bool $hasPreviousValue,
       mixed $previousValue,
       mixed $intermediateValue,
       mixed $finalValue
   ): RuleOverwrite {
       return new RuleOverwrite(
           $field,
           $previousRuleId,
           $laterRuleId,
           $previousProcessingIndex,
           $laterProcessingIndex,
           OverwriteClassification::POSSIBLE,
           $reason,
           $hasPreviousValue,
           $previousValue,
           $intermediateValue,
           $finalValue
       );
   }
}
