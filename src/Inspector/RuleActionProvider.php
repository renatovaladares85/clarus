<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleActionProvider
{
    /**
     * @param list<int> $ruleIds
     * @return array<int, list<ConfiguredAction>>
     */
   public function forRuleIds(array $ruleIds): array {
       /** @var \DBmysql $DB */
       global $DB;

       $ruleIds = array_values(array_unique(array_filter(
           array_map(static fn (int $ruleId): int => $ruleId, $ruleIds),
           static fn (int $ruleId): bool => $ruleId > 0
       )));
       $actionsByRule = array_fill_keys($ruleIds, []);
      if ($ruleIds === []) {
          return $actionsByRule;
      }

       $ruleAction = new \RuleAction();
       $iterator = $DB->request([
           'SELECT' => ['id', 'rules_id', 'action_type', 'field', 'value'],
           'FROM' => $ruleAction->getTable(),
           'WHERE' => ['rules_id' => $ruleIds],
           'ORDER' => ['rules_id', 'id'],
       ]);
       $orders = [];
      foreach ($iterator as $row) {
          $ruleId = NativeField::integer($row['rules_id'] ?? 0);
         if (!array_key_exists($ruleId, $actionsByRule)) {
             continue;
         }

          $order = $orders[$ruleId] ?? 0;
          $actionsByRule[$ruleId][] = new ConfiguredAction(
              $ruleId,
              NativeField::integer($row['id'] ?? 0),
              NativeField::string($row['action_type'] ?? ''),
              NativeField::string($row['field'] ?? ''),
              $row['value'] ?? null,
              $order
          );
          $orders[$ruleId] = $order + 1;
      }

       return $actionsByRule;
   }
}
