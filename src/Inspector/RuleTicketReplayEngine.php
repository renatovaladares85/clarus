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

      foreach ($candidates as $processingIndex => $rule) {
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
}
