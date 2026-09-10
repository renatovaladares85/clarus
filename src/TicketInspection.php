<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleTicketInspector;

/** Coordinates the single global rule budget across enabled Ticket conditions. */
final class TicketInspection
{
   /** @var \Closure(\Ticket, int, InspectionOptions): InspectionResult */
   private readonly \Closure $inspect;

   /**
    * @param null|callable(\Ticket, int, InspectionOptions): InspectionResult $inspect
    */
   public function __construct(?callable $inspect = null) {
      if ($inspect !== null) {
         $this->inspect = \Closure::fromCallable($inspect);
         return;
      }

      $inspector = new RuleTicketInspector();
      $this->inspect = static fn (\Ticket $ticket, int $condition, InspectionOptions $options): InspectionResult
         => $inspector->inspect($ticket, $condition, $options);
   }

   /**
    * @param array<string, bool|int|string|list<array{field: string, direction: string}>> $settings
    * @return list<InspectionResult>
    */
   public function inspect(\Ticket $ticket, array $settings): array {
      $remaining = (int) $settings[ClarusConfig::RULE_LIMIT];
      $results = [];

      foreach ([
         [ClarusConfig::INCLUDE_ONADD, \RuleTicket::ONADD],
         [ClarusConfig::INCLUDE_ONUPDATE, \RuleTicket::ONUPDATE],
      ] as [$setting, $condition]) {
         if (!(bool) $settings[$setting]) {
            continue;
         }

         $result = ($this->inspect)(
            $ticket,
            $condition,
            new InspectionOptions($remaining, (bool) $settings[ClarusConfig::INCLUDE_ACTIONS])
         );
         $results[] = $result;
         $remaining -= $result->evaluatedCount;
      }

      return $results;
   }
}
