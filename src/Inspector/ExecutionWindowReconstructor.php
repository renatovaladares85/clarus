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
       $windows = [new ExecutionWindow(
           \RuleTicket::ONADD,
           $context,
           false,
           $baseLimitations,
           'onadd:retained-history'
       )];

       // Timestamp groups are durable later changes. They establish a
       // pre-change context, but a log row does not prove a RuleTicket pass.
       foreach ($this->groupsByTimestamp($changes) as $index => $group) {
           $windows[] = new ExecutionWindow(
               \RuleTicket::ONUPDATE,
               $context,
               false,
               array_merge($baseLimitations, [
                   'This retained Ticket-history group is a possible ONUPDATE context, not confirmation of a RuleTicket execution boundary.',
               ]),
               'onupdate:history:' . ($index + 1),
               $group
           );
          foreach ($group as $change) {
              $context = $context->with(
                  $change->field,
                  ContextValue::available(
                      $change->after,
                      'history:' . $change->id,
                      TicketContextBuilder::isPresentationSafeKey($change->field)
                  )
              );
          }
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
