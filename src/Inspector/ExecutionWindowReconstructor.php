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

       $context = $timeline->currentContext->withoutValues(self::REASON_MISSING_HISTORICAL_EVIDENCE);
       $reconstructedFields = [];
      foreach ($timeline->changes as $change) {
          // Chronological history: the first old value is the oldest retained
          // durable evidence for that field.
         if (isset($reconstructedFields[$change->field])) {
             continue;
         }
          $context = $context->with(
              $change->field,
              ContextValue::available($change->before, 'history:' . $change->id, TicketContextBuilder::isPresentationSafeKey($change->field))
          );
          $reconstructedFields[$change->field] = true;
      }

       $limitations = [
           'Replay is an inference from retained GLPI history, not proof that a RuleTicket execution occurred.',
       ];
       if ($reconstructedFields === []) {
          $limitations[] = 'No durable before/after field evidence is available for a historical replay input.';
       } else {
          $limitations[] = 'Only fields with durable before/after history are reconstructed; all remaining inputs are unknown.';
       }

       return new ExecutionWindow($condition, $context, false, $limitations);
   }
}
