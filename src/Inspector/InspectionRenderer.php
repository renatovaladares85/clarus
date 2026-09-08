<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Renders an immutable inspection result without exposing unsafe DTO values. */
final class InspectionRenderer
{
   public function render(InspectionResult $result): string {
      $html = "<section class='card card-body mb-3 clarus-inspection'>";
      $html .= '<h3>' . $this->escape(__('Rule inspection', 'clarus')) . '</h3>';
      $html .= "<p class='text-muted'>"
         . $this->escape(__('Current-state diagnostic only; it is not proof of historical rule execution.', 'clarus'))
         . '</p>';
      $html .= "<dl class='row'>";
      $html .= $this->definition(__('Condition', 'clarus'), $this->condition($result->condition));
      $html .= $this->definition(__('Candidates', 'clarus'), (string) $result->candidateCount);
      $html .= $this->definition(__('Evaluated', 'clarus'), (string) $result->evaluatedCount);
      $html .= $this->definition(__('Configured limit', 'clarus'), (string) $result->configuredLimit);
      $html .= '</dl>';

      if ($result->truncated) {
         $html .= "<div class='alert alert-warning' role='alert'>"
            . $this->escape(__('Results were truncated at the configured rule limit.', 'clarus'))
            . '</div>';
      }

      $html .= $this->limitations($result->limitations);
      foreach ($result->rules as $rule) {
         $html .= $this->rule($rule);
      }
      $html .= '</section>';

      return $html;
   }

   private function rule(RuleInspection $rule): string {
      $title = sprintf(
         '%s #%d — %s',
         __('Rule', 'clarus'),
         $rule->id,
         $rule->name === '' ? __('Unnamed', 'clarus') : $rule->name
      );
      $html = '<details class="mb-2">';
      $html .= '<summary>' . $this->escape($title) . '</summary>';
      $html .= "<dl class='row mt-2'>";
      $html .= $this->definition(__('Condition', 'clarus'), $this->condition($rule->condition));
      $html .= $this->definition(__('Evaluation', 'clarus'), $rule->evaluation->value);
      $html .= $this->definition(__('Ranking', 'clarus'), (string) $rule->ranking);
      $html .= $this->definition(__('Entity', 'clarus'), (string) $rule->entityId);
      $html .= $this->definition(__('Recursive', 'clarus'), $rule->recursive ? __('Yes') : __('No'));
      $html .= $this->definition(__('Matching mode', 'clarus'), $rule->matchingMode);
      $html .= '</dl>';
      $html .= $this->limitations($rule->limitations);
      $html .= $this->criteria($rule->criteria);
      $html .= $this->actions($rule->actions);
      $html .= '</details>';

      return $html;
   }

   /** @param list<CriterionInspection> $criteria */
   private function criteria(array $criteria): string {
      if ($criteria === []) {
         return '';
      }

      $html = '<h4>' . $this->escape(__('Criteria', 'clarus')) . '</h4>';
      $html .= "<table class='table table-sm'><thead><tr>";
      $html .= '<th>' . $this->escape(__('Criterion', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Operator', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Evaluation', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Limitation', 'clarus')) . '</th>';
      $html .= '</tr></thead><tbody>';
      foreach ($criteria as $criterion) {
         $html .= '<tr>';
         $html .= '<td>' . $this->escape($criterion->key) . '</td>';
         $html .= '<td>' . $this->escape((string) $criterion->operator) . '</td>';
         $html .= '<td>' . $this->escape($criterion->evaluation->value) . '</td>';
         $html .= '<td>' . $this->escape($criterion->reason ?? '') . '</td>';
         $html .= '</tr>';
      }
      $html .= '</tbody></table>';

      return $html;
   }

   /** @param list<ActionInspection> $actions */
   private function actions(array $actions): string {
      if ($actions === []) {
         return '';
      }

      $html = '<h4>' . $this->escape(__('Configured actions', 'clarus')) . '</h4>';
      $html .= "<table class='table table-sm'><thead><tr>";
      $html .= '<th>' . $this->escape(__('Action', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Field', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Support', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Evaluation', 'clarus')) . '</th>';
      $html .= '<th>' . $this->escape(__('Limitation', 'clarus')) . '</th>';
      $html .= '</tr></thead><tbody>';
      foreach ($actions as $action) {
         $html .= '<tr>';
         $html .= '<td>' . $this->escape($action->actionType) . '</td>';
         $html .= '<td>' . $this->escape($action->field) . '</td>';
         $html .= '<td>' . $this->escape($action->support->value) . '</td>';
         $html .= '<td>' . $this->escape($action->evaluation->value) . '</td>';
         $html .= '<td>' . $this->escape($action->reason ?? '') . '</td>';
         $html .= '</tr>';
      }
      $html .= '</tbody></table>';

      return $html;
   }

   /** @param list<string> $limitations */
   private function limitations(array $limitations): string {
      if ($limitations === []) {
         return '';
      }

      $html = "<ul class='text-muted'>";
      foreach ($limitations as $limitation) {
         $html .= '<li>' . $this->escape($limitation) . '</li>';
      }
      $html .= '</ul>';

      return $html;
   }

   private function definition(string $label, string $value): string {
      return '<dt class="col-sm-4">' . $this->escape($label) . '</dt>'
         . '<dd class="col-sm-8">' . $this->escape($value) . '</dd>';
   }

   private function condition(int $condition): string {
      return match ($condition) {
         \RuleTicket::ONADD    => 'ONADD',
         \RuleTicket::ONUPDATE => 'ONUPDATE',
         default                => (string) $condition,
      };
   }

   private function escape(string $value): string {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
