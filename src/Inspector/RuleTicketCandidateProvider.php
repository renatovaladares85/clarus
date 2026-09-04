<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleTicketCandidateProvider
{
    /** @return list<\RuleTicket> */
   public function candidates(\Ticket $ticket, int $condition): array {
       $entityId = NativeField::integer($ticket->fields['entities_id'] ?? 0);
       $collection = new \RuleTicketCollection($entityId);
       $collection->getCollectionDatas(1, 0, $condition);

      if (!$collection->RuleList instanceof \SingletonRuleList) {
          return [];
      }

       return array_values($collection->RuleList->list);
   }
}
