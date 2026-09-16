<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleTicketInspector
{
   public function __construct(
       private readonly TicketContextBuilder $contextBuilder = new TicketContextBuilder(),
       private readonly RuleTicketCandidateProvider $candidateProvider = new RuleTicketCandidateProvider(),
       private readonly RuleActionProvider $actionProvider = new RuleActionProvider(),
       private readonly RuleTicketActionAnalyzer $actionAnalyzer = new RuleTicketActionAnalyzer(),
       private readonly RuleTicketEvaluator $evaluator = new RuleTicketEvaluator(),
       private readonly TicketTimelineReader $timelineReader = new TicketTimelineReader(),
       private readonly ExecutionWindowReconstructor $windowReconstructor = new ExecutionWindowReconstructor(),
       private readonly RuleTicketReplayEngine $replayEngine = new RuleTicketReplayEngine()
   ) {
   }

   public function inspect(
       \Ticket $ticket,
       int $condition,
       ?InspectionOptions $options = null
   ): InspectionResult {
      if (!in_array($condition, [\RuleTicket::ONADD, \RuleTicket::ONUPDATE], true)) {
          throw new \InvalidArgumentException('Inspection condition must be RuleTicket::ONADD or RuleTicket::ONUPDATE.');
      }
      if ($ticket->isNewItem()) {
          throw new \InvalidArgumentException('Inspector requires a persisted Ticket.');
      }

       $options ??= new InspectionOptions();
       $persistedContext = $this->contextBuilder->build($ticket);
       $candidates = $this->candidateProvider->candidates($ticket, $condition);
       $candidateCount = count($candidates);
       $selected = array_slice($candidates, 0, $options->ruleLimit);
       $actionsByRule = $this->actionProvider->forRuleIds(array_map(
           static fn (\RuleTicket $rule): int => NativeField::integer($rule->fields['id'] ?? 0),
           $selected
       ));

       // The primary inspection always answers a current-state question. It
       // must not be tainted by a preceding simulated action.
       $rules = [];
      foreach ($selected as $rule) {
          $inspection = $this->evaluator->inspect($rule, $persistedContext, $condition);
         if ($options->includeActions) {
             $inspection = $inspection->withActions(
                 $this->actionAnalyzer->analyzeAll($actionsByRule[$inspection->id] ?? [], $persistedContext)
             );
         }
          $rules[] = $inspection;
      }

       $timeline = $this->timelineReader->read($ticket);
       $replays = [];
      foreach ($this->windowReconstructor->reconstructAll($timeline) as $window) {
          $entityId = $window->candidateEntityId;
         if ($entityId === null) {
             $replay = $this->replayEngine->replay($window, [], [])->withLimitations([
                 'Native RuleTicket candidate selection is indeterminate because the historical entity is unavailable.',
             ]);
         } else {
             $windowCandidates = $this->candidateProvider->candidatesForEntity(
                 $entityId,
                 $window->condition
             );
             $windowActions = $this->actionProvider->forRuleIds(array_map(
                 static fn (\RuleTicket $rule): int => NativeField::integer($rule->fields['id'] ?? 0),
                 $windowCandidates
             ));
             // Never apply the presentation limit to replay: an omitted native
             // rule could alter the input of every subsequent rule.
             $replay = $this->replayEngine->replay($window, $windowCandidates, $windowActions);
         }
          $replays[] = $replay->withLaterChanges(array_map(
              static fn (TimelineFieldChange $change): LaterTicketChange => new LaterTicketChange($change),
              $window->evidence
          ));
      }

       $matchingReplays = array_values(array_filter(
           $replays,
           static fn (RuleTicketReplay $replay): bool => $replay->window->condition === $condition
       ));
       $replay = $matchingReplays[0] ?? $this->replayEngine->replay(
           $this->windowReconstructor->reconstruct($timeline, $condition),
           [],
           []
       );
       $rules = array_map(
           static function (RuleInspection $rule) use ($matchingReplays): RuleInspection {
               $contexts = [];
            foreach ($matchingReplays as $contextReplay) {
                   $replayRule = $contextReplay->rule($rule->id);
               if ($replayRule !== null) {
                      $contexts[$contextReplay->window->id] = $replayRule;
               }
            }

               return $rule->withReplayContexts($contexts);
           },
           $rules
       );

       return new InspectionResult(
           $ticket->getID(),
           $condition,
           $options->ruleLimit,
           $candidateCount,
           count($rules),
           $candidateCount > count($rules),
           $rules,
           array_merge([
               'Primary results describe current Ticket snapshot compatibility, not historical rule execution.',
               'Historical replay uses only durable before/after GLPI history and fails closed for missing inputs.',
               'Configured rule actions are never executed by inspection.',
               $options->includeActions
                   ? 'Action reflection describes only the current Ticket snapshot, not historical causality.'
                   : 'Configured rule actions were not included in this inspection.',
           ], $replay->limitations),
           $replay->overwrites,
           $replay,
           $replays
       );
   }
}
