<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Replays only characterized, pure action transitions. Native mutating rule
 * processing is never invoked here.
 */
final class RuleTicketReplayEngine
{
   public function __construct(
       private readonly RuleTicketEvaluator $evaluator = new RuleTicketEvaluator(),
       private readonly RuleTicketInputPreparer $inputPreparer = new RuleTicketInputPreparer(),
       private readonly RuleEffectProjector $effectProjector = new RuleEffectProjector(),
       private readonly SequentialOverwriteAnalyzer $overwriteAnalyzer = new SequentialOverwriteAnalyzer()
   ) {
   }

   /**
    * @param list<\RuleTicket> $candidates Native collection order
    * @param array<int, list<ConfiguredAction>> $actionsByRule
    */
   public function replay(ExecutionWindow $window, array $candidates, array $actionsByRule): RuleTicketReplay {
       $context = $this->inputPreparer->prepare($window->inputContext);
       $rules = [];
       $limitations = $window->limitations;
       $onlyCriteria = $window->onlyCriteria;

      if ($onlyCriteria !== null && !$window->updateInputKnown) {
          $limitations[] = 'Historical ONUPDATE replay was not evaluated because retained history cannot distinguish caller input changes from values produced later by rules.';
          return new RuleTicketReplay($window, $rules, $limitations, []);
      }

      foreach ($candidates as $processingIndex => $rule) {
         if ($onlyCriteria !== null && !$this->evaluator->isEligibleForUpdate($rule, $onlyCriteria)) {
             $inspection = $this->evaluator->skippedForUpdateScope($rule, $window->condition);
             $rules[] = $inspection->withSequentialStep(new SequentialRuleStep(
                 $processingIndex,
                 $context,
                 $context,
                 []
             ));
             continue;
         }

          $inspection = $this->evaluator->inspect($rule, $context, $window->condition);
          $actions = $actionsByRule[$inspection->id] ?? [];
          $projection = $this->effectProjector->project($inspection->evaluation, $actions, $context);
          $rules[] = $inspection->withSequentialStep(new SequentialRuleStep(
              $processingIndex,
              $context,
              $projection->outputContext,
              $projection->effects
          ));
          // RuleTicketCollection feeds each rule output through
          // prepareInputDataForProcess() before it becomes the next input.
          $context = $this->inputPreparer->prepare($projection->outputContext);

         if ($onlyCriteria !== null) {
            if ($this->hasIndeterminateEffect($projection->effects)) {
                 $limitations[] = sprintf(
                     'Replay stopped after rule #%d because an unsupported or indeterminate action makes native ONUPDATE only_criteria propagation unknowable.',
                     $inspection->id
                 );
                 break;
            }
             $onlyCriteria = $this->updateOnlyCriteria($rule, $projection->effects, $onlyCriteria);
         }

         if ($projection->stopProcessing) {
             $limitations[] = sprintf('Replay stopped after rule #%d because its native stop-processing action is reproducible.', $inspection->id);
             break;
         }
         if ($projection->stopProcessingIndeterminate) {
             $limitations[] = sprintf('Replay stopped after rule #%d because stop-processing semantics are not reproducible safely.', $inspection->id);
             break;
         }
      }

       return new RuleTicketReplay(
           $window,
           $rules,
           $limitations,
           $this->overwriteAnalyzer->analyze($rules)
       );
   }

   /** @param list<ProjectedRuleEffect> $effects */
   private function hasIndeterminateEffect(array $effects): bool {
      foreach ($effects as $effect) {
         if (in_array($effect->status, [ProjectionStatus::INDETERMINATE, ProjectionStatus::UNSUPPORTED], true)) {
            return true;
         }
      }

       return false;
   }

   /**
    * Models Rule::updateOnlyCriteria(): an action which changes output adds
    * its field and native linked criteria to the scope for later rules.
    *
    * @param list<ProjectedRuleEffect> $effects
    * @param list<string> $onlyCriteria
    * @return list<string>
    */
   private function updateOnlyCriteria(\RuleTicket $rule, array $effects, array $onlyCriteria): array {
      foreach ($effects as $effect) {
         if ($effect->status !== ProjectionStatus::APPLIED
             || !$effect->hasNextValue
             || ($effect->hasPreviousValue && $effect->previousValue == $effect->nextValue)) {
             continue;
         }

          $onlyCriteria[] = $effect->field;
          $criteria = $rule->getCriteria($effect->field);
          $linkedCriteria = $criteria['linked_criteria'] ?? [];
         if (!is_array($linkedCriteria)) {
             $linkedCriteria = [$linkedCriteria];
         }
         foreach ($linkedCriteria as $linkedCriterion) {
             $onlyCriteria[] = NativeField::string($linkedCriterion);
         }
      }

       return array_values(array_unique($onlyCriteria));
   }
}
