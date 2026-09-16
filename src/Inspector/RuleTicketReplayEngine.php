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
       $limitations = $window->limitations;

      if ($window->evidenceLevel === ReplayEvidenceLevel::INDETERMINATE) {
          $limitations[] = 'Historical replay was not evaluated because the required execution input is indeterminate.';
          return new RuleTicketReplay($window, [], $limitations, []);
      }

      if ($window->evidenceLevel === ReplayEvidenceLevel::POSSIBLE_REPLAY) {
         if ($window->hypotheses === []) {
              $limitations[] = 'Historical replay is indeterminate because retained history does not provide a bounded candidate input with one defensible native candidate sequence.';
              return new RuleTicketReplay($window, [], $limitations, []);
         }
          $candidateReplays = array_map(
              fn (ReplayHypothesis $hypothesis): RuleTicketReplay => $this->replayOne(
                  $window,
                  $hypothesis->inputContext,
                  $hypothesis->onlyCriteria,
                  $candidates,
                  $actionsByRule
              ),
              $window->hypotheses
          );
          $signatures = array_values(array_unique(array_map(
              fn (RuleTicketReplay $replay): string => $this->outcomeSignature($replay),
              $candidateReplays
          )));
         if (count($signatures) !== 1) {
             $limitations[] = 'Historical replay is indeterminate because bounded retained-history hypotheses produce different rule outcomes.';
             return new RuleTicketReplay($window, [], $limitations, []);
         }
          $representative = $candidateReplays[0];
          $limitations = array_merge($limitations, $representative->limitations, [
              sprintf(
                  'This is a possible replay: %d bounded retained-history hypotheses agree, but the observed changes do not prove a RuleTicket execution boundary.',
                  count($candidateReplays)
              ),
          ]);
          return new RuleTicketReplay($window, $representative->rules, $limitations, $representative->overwrites);
      }

       $replay = $this->replayOne($window, $window->inputContext, $window->onlyCriteria, $candidates, $actionsByRule);
       return new RuleTicketReplay($window, $replay->rules, array_merge($limitations, $replay->limitations), $replay->overwrites);
   }

   /**
    * @param list<\RuleTicket> $candidates Native collection order
    * @param array<int, list<ConfiguredAction>> $actionsByRule
    * @param null|list<string> $onlyCriteria
    */
   private function replayOne(
       ExecutionWindow $window,
       TicketContext $inputContext,
       ?array $onlyCriteria,
       array $candidates,
       array $actionsByRule
   ): RuleTicketReplay {
       $context = $this->inputPreparer->prepare($inputContext);
       $rules = [];
       $limitations = [];

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

   private function outcomeSignature(RuleTicketReplay $replay): string {
      $rules = [];
      foreach ($replay->rules as $rule) {
          $effects = [];
          $step = $rule->sequentialStep;
         foreach ($step === null ? [] : $step->effects as $effect) {
             $effects[] = [$effect->actionId, $effect->field, $effect->status->value, $effect->nextValue];
         }
          $rules[] = [$rule->id, $rule->evaluation->value, $effects];
      }

       return serialize([$rules, $replay->overwrites]);
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
