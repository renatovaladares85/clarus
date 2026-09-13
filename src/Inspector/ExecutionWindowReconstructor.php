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
           'unavailable:' . $condition
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
       // creation. It cannot establish the caller input that preceded ONADD
       // actions, so never feed those retained before values into ONADD.
       $onaddCandidateEntity = $persistedEntity->state === ContextState::AVAILABLE
           ? NativeField::integer($persistedEntity->value)
           : null;
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
           true,
           $onaddCandidateEntity
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
               false,
               $updateContext->get('entities_id')->state === ContextState::AVAILABLE
                   ? NativeField::integer($updateContext->get('entities_id')->value)
                   : null
           );
           $context = $updateContext;
       }

       return $windows;
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
