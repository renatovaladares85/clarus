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
         __('Rule #%d — %s', 'clarus'),
         $rule->id,
         $rule->name === '' ? __('Unnamed', 'clarus') : $rule->name
      );
      $html = '<details class="mb-2">';
      $html .= '<summary>' . $this->escape($title) . '</summary>';
      $html .= "<dl class='row mt-2'>";
      $html .= $this->definition(__('Condition', 'clarus'), $this->condition($rule->condition));
      $html .= $this->definition(__('Evaluation', 'clarus'), $this->evaluation($rule->evaluation));
      $html .= $this->definition(__('Ranking', 'clarus'), (string) $rule->ranking);
      $html .= $this->definition(__('Entity', 'clarus'), (string) $rule->entityId);
      $html .= $this->definition(__('Recursive', 'clarus'), $rule->recursive ? __('Yes') : __('No'));
      $html .= $this->definition(__('Matching mode', 'clarus'), $this->matchingMode($rule->matchingMode));
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
         $html .= '<td>' . $this->escape($this->criterion($criterion->key)) . '</td>';
         $html .= '<td>' . $this->escape($this->operator($criterion->operator, $criterion->key)) . '</td>';
         $html .= '<td>' . $this->escape($this->evaluation($criterion->evaluation)) . '</td>';
         $html .= '<td>' . $this->escape($criterion->reason === null ? '' : $this->limitation($criterion->reason)) . '</td>';
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
         $html .= '<td>' . $this->escape($this->actionType($action->actionType)) . '</td>';
         $html .= '<td>' . $this->escape($this->actionField($action->field)) . '</td>';
         $html .= '<td>' . $this->escape($this->actionSupport($action->support)) . '</td>';
         $html .= '<td>' . $this->escape($this->actionEvaluation($action->evaluation)) . '</td>';
         $html .= '<td>' . $this->escape($action->reason === null ? '' : $this->limitation($action->reason)) . '</td>';
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
         $html .= '<li>' . $this->escape($this->limitation($limitation)) . '</li>';
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
         \RuleTicket::ONADD    => __('On ticket creation (ONADD)', 'clarus'),
         \RuleTicket::ONUPDATE => __('On ticket update (ONUPDATE)', 'clarus'),
         default                => sprintf(__('Unknown condition (%d)', 'clarus'), $condition),
      };
   }

   private function evaluation(Evaluation $evaluation): string {
      return match ($evaluation) {
         Evaluation::MATCH         => __('Matches current state', 'clarus'),
         Evaluation::NO_MATCH      => __('Does not match current state', 'clarus'),
         Evaluation::INDETERMINATE => __('Indeterminate', 'clarus'),
      };
   }

   private function actionEvaluation(ActionEvaluation $evaluation): string {
      return match ($evaluation) {
         ActionEvaluation::REFLECTED     => __('Reflected in current state', 'clarus'),
         ActionEvaluation::NOT_REFLECTED => __('Not reflected in current state', 'clarus'),
         ActionEvaluation::INDETERMINATE => __('Indeterminate', 'clarus'),
      };
   }

   private function actionSupport(ActionSupport $support): string {
      return match ($support) {
         ActionSupport::SUPPORTED               => __('Supported', 'clarus'),
         ActionSupport::INDETERMINATE_BY_DESIGN => __('Indeterminate by design', 'clarus'),
         ActionSupport::UNSUPPORTED             => __('Unsupported', 'clarus'),
      };
   }

   private function matchingMode(string $matchingMode): string {
      return match ($matchingMode) {
         'AND'   => __('All criteria (AND)', 'clarus'),
         'OR'    => __('Any criterion (OR)', 'clarus'),
         default => sprintf(__('Unknown matching mode (%s)', 'clarus'), $matchingMode),
      };
   }

   private function criterion(string $key): string {
      $criteria = (new \RuleTicket())->getCriterias();
      $label = $criteria[$key]['name'] ?? null;

      return is_string($label) ? $label : sprintf(__('Unknown criterion (%s)', 'clarus'), $key);
   }

   private function operator(int $operator, string $criterion): string {
      $label = \RuleCriteria::getConditionByID($operator, \RuleTicket::class, $criterion);

      return $label !== ''
         ? $label
         : sprintf(__('Unknown operator (%d)', 'clarus'), $operator);
   }

   private function actionType(string $actionType): string {
      $label = \RuleAction::getActionByID($actionType);

      return $label !== ''
         ? $label
         : sprintf(__('Unknown action (%s)', 'clarus'), $actionType);
   }

   private function actionField(string $field): string {
      $actions = (new \RuleTicket())->getActions();
      $label = $actions[$field]['name'] ?? null;

      return is_string($label) ? $label : sprintf(__('Unknown field (%s)', 'clarus'), $field);
   }

   private function limitation(string $reason): string {
      return match ($reason) {
         'Value cannot be reconstructed defensibly from the persisted Ticket state.'
            => __('This value cannot be reconstructed defensibly from the persisted Ticket state.', 'clarus'),
         'Criterion is not part of the reconstructable Ticket context.'
            => __('This criterion is not part of the reconstructable Ticket context.', 'clarus'),
         'Native RuleTicket criterion evaluation did not return a result.'
            => __('Native RuleTicket criterion evaluation did not return a result.', 'clarus'),
         'Rule has an unsupported native matching mode.'
            => __('The rule uses an unsupported native matching mode.', 'clarus'),
         'Native Rule processing rejects rules without criteria.'
            => __('Native Rule processing rejects rules without criteria.', 'clarus'),
         'UPDATE eligibility depends on the original change set, which is not present on a persisted Ticket.'
            => __('Update eligibility depends on the original change set, which is not present on a persisted Ticket.', 'clarus'),
         'Results describe the current reconstructable Ticket state, not historical rule execution.'
            => __('Results describe the current reconstructable Ticket state, not historical rule execution.', 'clarus'),
         'Configured rule actions are never executed by inspection.'
            => __('Configured rule actions are never executed by inspection.', 'clarus'),
         'Action reflection describes only the current Ticket snapshot, not historical causality.'
            => __('Action reflection describes only the current Ticket snapshot, not historical causality.', 'clarus'),
         'Configured rule actions were not included in this inspection.'
            => __('Configured rule actions were not included in this inspection.', 'clarus'),
         'invalid_action_configuration'
            => __('The configured action is invalid and cannot be evaluated.', 'clarus'),
         'invalid_configured_value'
            => __('The configured action value is invalid for a safe current-state comparison.', 'clarus'),
         'current_value_unavailable'
            => __('The current Ticket value is unavailable for comparison.', 'clarus'),
         'current_value_invalid'
            => __('The current Ticket value is invalid for comparison.', 'clarus'),
         'dynamic_runtime_input_required'
            => __('The action requires runtime input that cannot be reconstructed.', 'clarus'),
         'regex_capture_not_reconstructible'
            => __('The regular-expression capture result cannot be reconstructed.', 'clarus'),
         'historical_priority_matrix_input_missing'
            => __('The historical priority matrix input is unavailable.', 'clarus'),
         'runtime_lookup_not_reconstructible'
            => __('The runtime lookup result cannot be reconstructed.', 'clarus'),
         'validation_side_effect_not_reconstructible'
            => __('The validation side effect cannot be reconstructed.', 'clarus'),
         'transient_processing_flag'
            => __('The transient processing state cannot be reconstructed.', 'clarus'),
         'template_execution_result_not_reconstructible'
            => __('The template execution result cannot be reconstructed.', 'clarus'),
         'special_relation_semantics_not_supported'
            => __('The special relation semantics are not supported by this inspection.', 'clarus'),
         'unsupported_action_semantics'
            => __('The action semantics are not supported by this inspection.', 'clarus'),
         default => $reason,
      };
   }

   private function escape(string $value): string {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
