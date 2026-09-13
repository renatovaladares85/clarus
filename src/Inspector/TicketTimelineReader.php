<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Reads raw GLPI history rows; it never creates history or changes a Ticket. */
final class TicketTimelineReader
{
   public function __construct(private readonly TicketContextBuilder $contextBuilder = new TicketContextBuilder()) {
   }

   public function read(\Ticket $ticket): TicketTimeline {
       $currentContext = $this->contextBuilder->build($ticket);
       /** @var \DBmysql $DB */
       global $DB;

       $fieldsBySearchOption = $this->fieldsBySearchOption($ticket, $currentContext);
       $changes = [];
       $log = new \Log();
       $iterator = $DB->request([
           'SELECT' => ['id', 'date_mod', 'id_search_option', 'old_value', 'new_value'],
           'FROM' => $log->getTable(),
           'WHERE' => [
               'items_id' => $ticket->getID(),
               'itemtype' => $ticket->getType(),
           ],
           'ORDER' => 'id ASC',
       ]);
      foreach ($iterator as $row) {
          $searchOption = NativeField::integer($row['id_search_option'] ?? 0);
         if (!isset($fieldsBySearchOption[$searchOption])) {
             continue;
         }

          [$field, $linkedValue] = $fieldsBySearchOption[$searchOption];
          $before = $this->historyValue($row['old_value'] ?? null, $linkedValue);
          $after = $this->historyValue($row['new_value'] ?? null, $linkedValue);
         if ($before === null || $after === null) {
             continue;
         }

          $changes[] = new TimelineFieldChange(
              NativeField::integer($row['id'] ?? 0),
              $field,
              $before,
              $after,
              NativeField::string($row['date_mod'] ?? '')
          );
      }

       return new TicketTimeline($currentContext, $changes);
   }

   /** @return array<int, array{string, bool}> */
   private function fieldsBySearchOption(\Ticket $ticket, TicketContext $context): array {
       $fields = [];
       $knownKeys = $context->values();
      foreach (\Search::getOptions($ticket->getType()) as $id => $option) {
         if (!is_int($id) || !is_array($option) || !isset($option['table'])) {
             continue;
         }

          $directField = $option['field'] ?? null;
         if ($option['table'] === $ticket->getTable() && is_string($directField) && isset($knownKeys[$directField])) {
             $fields[$id] = [$directField, false];
             continue;
         }

          $linkField = $option['linkfield'] ?? null;
         if (is_string($linkField) && isset($knownKeys[$linkField])) {
             // GLPI stores dropdown log values as "display name (id)". Only
             // retain an unambiguous trailing numeric identifier.
             $fields[$id] = [$linkField, true];
         }
      }

       return $fields;
   }

   private function historyValue(mixed $value, bool $linkedValue): mixed {
      if (!is_string($value)) {
          return null;
      }
      if (!$linkedValue) {
          return stripslashes($value);
      }

      if (preg_match('/\\((-?\\d+)\\)$/D', $value, $matches) !== 1) {
          return null;
      }

       return (int) $matches[1];
   }
}
