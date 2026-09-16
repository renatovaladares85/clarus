<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Reconstructs only values directly recorded by GLPI history. Missing fields
 * stay unknown: the saved Ticket must never become a synthetic past input.
 */
final class ExecutionWindowReconstructor
{
   public const REASON_MISSING_HISTORICAL_EVIDENCE = 'historical_input_not_persisted';
   private const MAX_UPDATE_HYPOTHESES_FIELDS = 6;

   public function reconstruct(TicketTimeline $timeline, int $condition): ExecutionWindow {
      if (!in_array($condition, [\RuleTicket::ONADD, \RuleTicket::ONUPDATE], true)) {
          throw new \InvalidArgumentException('Execution window condition must be RuleTicket::ONADD or RuleTicket::ONUPDATE.');
      }

       $windows = array_values(array_filter(
           $this->reconstructAll($timeline),
           static fn (ExecutionWindow $window): bool => $window->condition === $condition
       ));
      if ($windows !== []) {
          return $windows[0];
      }

       return new ExecutionWindow(
           $condition,
           $timeline->currentContext->withoutValues(self::REASON_MISSING_HISTORICAL_EVIDENCE),
           false,
           ['No durable before/after field evidence is available for a historical replay input.'],
           'unavailable:' . $condition,
           [],
           null,
           null,
           ReplayEvidenceLevel::INDETERMINATE
       );
   }

   /** @return list<ExecutionWindow> */
   public function reconstructAll(TicketTimeline $timeline): array {
       $changes = $timeline->changes;
       usort($changes, static fn (TimelineFieldChange $left, TimelineFieldChange $right): int => [
           $left->occurredAt, $left->id,
       ] <=> [
           $right->occurredAt, $right->id]);

       $context = $timeline->currentContext->withoutValues(self::REASON_MISSING_HISTORICAL_EVIDENCE);
       // RuleTicketCollection requires an entity before it can obtain its
       // native, inherited candidate sequence. GLPI keeps the Ticket entity
       // as durable record identity; a retained entities_id history row below
       // always replaces this value for earlier/later reconstructed windows.
       $persistedEntity = $timeline->currentContext->get('entities_id');
      if ($persistedEntity->state === ContextState::AVAILABLE) {
          $context = $context->with(
              'entities_id',
              ContextValue::available($persistedEntity->value, 'ticket:entity-selection', true)
          );
      }
       $reconstructedFields = [];
      foreach ($changes as $change) {
         if (isset($reconstructedFields[$change->field])) {
             continue;
         }
          $context = $context->with(
              $change->field,
              ContextValue::available(
                  $change->before,
                  'history:' . $change->id,
                  TicketContextBuilder::isPresentationSafeKey($change->field)
              )
          );
          $reconstructedFields[$change->field] = true;
      }

       $baseLimitations = [
           'Replay is an inference from retained GLPI history, not proof that a RuleTicket execution occurred.',
           $reconstructedFields === []
               ? 'No durable before/after field evidence is available for a historical replay input.'
               : 'Only fields with durable before/after history are reconstructed; all remaining inputs are unknown.',
       ];
       // Later Ticket history records a value that was already persisted after
       // creation. It cannot establish either the caller input or entity that
       // preceded ONADD actions, so never use the current Ticket entity here.
       $windows = [new ExecutionWindow(
           \RuleTicket::ONADD,
           $timeline->currentContext->withoutValues(self::REASON_MISSING_HISTORICAL_EVIDENCE),
           false,
           array_merge($baseLimitations, [
               'ONADD was not replayed because retained later history cannot establish the pre-creation RuleTicket input.',
           ]),
           'onadd:input-not-reconstructable',
           [],
           null,
           null,
           ReplayEvidenceLevel::INDETERMINATE
       )];

       // Ticket::prepareInputForUpdate() receives the incoming values, not
       // the persisted pre-change snapshot. A timestamp group remains only a
       // possible execution boundary: GLPI history cannot distinguish a
       // caller change from output written by a prior rule in that execution.
       foreach ($this->groupsByTimestamp($changes) as $index => $group) {
           $updateContext = $context;
           $onlyCriteria = [];
          foreach ($group as $change) {
              $updateContext = $updateContext->with(
                  $change->field,
                  ContextValue::available(
                      $change->after,
                      'history:' . $change->id,
                      TicketContextBuilder::isPresentationSafeKey($change->field)
                  )
              );
             if ($change->before != $change->after) {
                 $onlyCriteria[$change->field] = true;
             }
          }
           $hypotheses = $this->buildUpdateHypotheses($context, $group);
           $hasEntityChange = in_array('entities_id', array_keys($onlyCriteria), true);
           $windows[] = new ExecutionWindow(
               \RuleTicket::ONUPDATE,
               $updateContext,
               false,
               array_merge($baseLimitations, [
                   'This retained Ticket-history group is a possible ONUPDATE context, not confirmation of a RuleTicket execution boundary.',
               ]),
               'onupdate:history:' . ($index + 1),
               $group,
               array_keys($onlyCriteria),
               !$hasEntityChange && $context->get('entities_id')->state === ContextState::AVAILABLE
                   ? NativeField::integer($context->get('entities_id')->value)
                   : null,
               ReplayEvidenceLevel::POSSIBLE_REPLAY,
               $hypotheses
           );
           $context = $updateContext;
       }

       return $windows;
   }

   /**
    * A retained timestamp may contain a mix of caller input and in-engine
    * output. Enumerate only the bounded non-empty subsets of observed changed
    * fields; an entity change is excluded because every subset could require a
    * different native candidate sequence.
    *
    * @param list<TimelineFieldChange> $group
    * @return list<ReplayHypothesis>
    */
   private function buildUpdateHypotheses(TicketContext $before, array $group): array {
       $changes = [];
      foreach ($group as $change) {
         if ($change->before != $change->after) {
             $changes[$change->field] = $change;
         }
      }
      if ($changes === []
          || array_key_exists('entities_id', $changes)
          || count($changes) > self::MAX_UPDATE_HYPOTHESES_FIELDS) {
          return [];
      }

       $changes = array_values($changes);
       $hypotheses = [];
      for ($mask = 1; $mask < (1 << count($changes)); $mask++) {
          $context = $before;
          $onlyCriteria = [];
         foreach ($changes as $index => $change) {
            if (($mask & (1 << $index)) === 0) {
                continue;
            }
             $context = $context->with(
                 $change->field,
                 ContextValue::available(
                     $change->after,
                     'history:' . $change->id,
                     TicketContextBuilder::isPresentationSafeKey($change->field)
                 )
             );
             $onlyCriteria[] = $change->field;
         }
          $hypotheses[] = new ReplayHypothesis($context, $onlyCriteria);
      }

       return $hypotheses;
   }

   /**
    * @param list<TimelineFieldChange> $changes
    * @return list<list<TimelineFieldChange>>
    */
   private function groupsByTimestamp(array $changes): array {
       $groups = [];
      foreach ($changes as $change) {
          $groups[$change->occurredAt][] = $change;
      }

       return array_values($groups);
   }
}
