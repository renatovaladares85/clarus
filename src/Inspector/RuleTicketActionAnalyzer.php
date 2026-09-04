<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleTicketActionAnalyzer
{
   public const REASON_INVALID_CONFIGURATION = 'invalid_action_configuration';
   public const REASON_INVALID_CONFIGURED_VALUE = 'invalid_configured_value';
   public const REASON_CURRENT_VALUE_UNAVAILABLE = 'current_value_unavailable';
   public const REASON_CURRENT_VALUE_INVALID = 'current_value_invalid';
   public const REASON_DYNAMIC_INPUT = 'dynamic_runtime_input_required';
   public const REASON_REGEX = 'regex_capture_not_reconstructible';
   public const REASON_PRIORITY = 'historical_priority_matrix_input_missing';
   public const REASON_LOOKUP = 'runtime_lookup_not_reconstructible';
   public const REASON_VALIDATION = 'validation_side_effect_not_reconstructible';
   public const REASON_TRANSIENT = 'transient_processing_flag';
   public const REASON_TEMPLATE = 'template_execution_result_not_reconstructible';
   public const REASON_SPECIAL_RELATION = 'special_relation_semantics_not_supported';
   public const REASON_UNSUPPORTED = 'unsupported_action_semantics';

    /** @var list<string> */
   private const INTEGER_ASSIGN_FIELDS = [
       'itilcategories_id',
       'type',
       'urgency',
       'impact',
       'priority',
       'status',
       'locations_id',
       'requesttypes_id',
       'global_validation',
       'validation_percent',
       'slas_id_ttr',
       'slas_id_tto',
       'olas_id_ttr',
       'olas_id_tto',
   ];

    /** @var list<string> */
   private const RELATION_FIELDS = [
       '_users_id_requester',
       '_groups_id_requester',
       '_users_id_assign',
       '_groups_id_assign',
       '_suppliers_id_assign',
       '_users_id_observer',
       '_groups_id_observer',
   ];

    /** @var list<string> */
   private const DELETE_FIELDS = [
       'time_to_resolve',
       'time_to_own',
       'internal_time_to_resolve',
       'internal_time_to_own',
   ];

    /** @var array<string, string> */
   private const INDETERMINATE_TYPES = [
       'compute' => self::REASON_PRIORITY,
       'fromuser' => self::REASON_DYNAMIC_INPUT,
       'defaultfromuser' => self::REASON_DYNAMIC_INPUT,
       'fromitem' => self::REASON_DYNAMIC_INPUT,
       'regex_result' => self::REASON_REGEX,
       'affectbyip' => self::REASON_LOOKUP,
       'affectbyfqdn' => self::REASON_LOOKUP,
       'affectbymac' => self::REASON_LOOKUP,
       'add_validation' => self::REASON_VALIDATION,
       'do_not_compute' => self::REASON_TRANSIENT,
   ];

    /** @var array<string, string> */
   private const INDETERMINATE_FIELDS = [
       'solution_template' => self::REASON_TEMPLATE,
       'task_template' => self::REASON_TEMPLATE,
       'itilfollowup_template' => self::REASON_TEMPLATE,
       '_stop_rules_processing' => self::REASON_TRANSIENT,
   ];

    /** @var list<string> */
   private const UNSUPPORTED_FIELDS = [
       'assign_appliance',
       'assign_project',
       'assign_contract',
   ];

    /**
     * @param list<ConfiguredAction> $actions
     * @return list<ActionInspection>
     */
   public function analyzeAll(array $actions, TicketContext $context): array {
       return array_map(
           fn (ConfiguredAction $action): ActionInspection => $this->analyze($action, $context),
           $actions
       );
   }

   public function analyze(ConfiguredAction $action, TicketContext $context): ActionInspection {
      if ($action->actionId < 1 || $action->ruleId < 1 || $action->actionType === '' || $action->field === '') {
          return $this->indeterminate($action, ActionSupport::UNSUPPORTED, self::REASON_INVALID_CONFIGURATION);
      }

      if (isset(self::INDETERMINATE_TYPES[$action->actionType])) {
          return $this->indeterminate(
              $action,
              ActionSupport::INDETERMINATE_BY_DESIGN,
              self::INDETERMINATE_TYPES[$action->actionType]
          );
      }

      if (isset(self::INDETERMINATE_FIELDS[$action->field])) {
          return $this->indeterminate(
              $action,
              ActionSupport::INDETERMINATE_BY_DESIGN,
              self::INDETERMINATE_FIELDS[$action->field]
          );
      }

      if (in_array($action->field, self::UNSUPPORTED_FIELDS, true)) {
          return $this->indeterminate($action, ActionSupport::UNSUPPORTED, self::REASON_SPECIAL_RELATION);
      }

      if ($action->actionType === 'assign' && in_array($action->field, self::INTEGER_ASSIGN_FIELDS, true)) {
          return $this->compareInteger($action, $context->get($action->field));
      }

      if (in_array($action->actionType, ['assign', 'append'], true)
          && in_array($action->field, self::RELATION_FIELDS, true)) {
          return $this->compareMembership($action, $context->get($action->field));
      }

      if ($action->actionType === 'delete' && in_array($action->field, self::DELETE_FIELDS, true)) {
          return $this->compareDelete($action, $context->get($action->field));
      }

       return $this->indeterminate($action, ActionSupport::UNSUPPORTED, self::REASON_UNSUPPORTED);
   }

   private function compareInteger(ConfiguredAction $action, ContextValue $current): ActionInspection {
       $expected = $this->integerOrNull($action->configuredValue);
      if ($expected === null) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_INVALID_CONFIGURED_VALUE);
      }
      if ($current->state === ContextState::INDETERMINATE) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_UNAVAILABLE);
      }

       $currentValue = $this->integerOrNull($current->value);
      if ($currentValue === null) {
         if ($current->value === null) {
             return $this->determinate($action, $current, $expected, null, false);
         }
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_INVALID);
      }

       return $this->determinate($action, $current, $expected, $currentValue, $expected === $currentValue);
   }

   private function compareMembership(ConfiguredAction $action, ContextValue $current): ActionInspection {
       $expected = $this->integerOrNull($action->configuredValue);
      if ($expected === null || $expected < 1) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_INVALID_CONFIGURED_VALUE);
      }
      if ($current->state === ContextState::INDETERMINATE) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_UNAVAILABLE);
      }
      if (!is_array($current->value)) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_INVALID);
      }

       $currentIds = [];
      foreach ($current->value as $value) {
          $id = $this->integerOrNull($value);
         if ($id === null) {
             return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_INVALID);
         }
          $currentIds[] = $id;
      }

       return $this->determinate(
           $action,
           $current,
           $expected,
           $currentIds,
           in_array($expected, $currentIds, true)
       );
   }

   private function compareDelete(ConfiguredAction $action, ContextValue $current): ActionInspection {
      if ($current->state === ContextState::INDETERMINATE) {
          return $this->indeterminate($action, ActionSupport::SUPPORTED, self::REASON_CURRENT_VALUE_UNAVAILABLE);
      }

       return new ActionInspection(
           $action->actionId,
           $action->actionType,
           $action->field,
           ActionSupport::SUPPORTED,
           $current->value === null ? ActionEvaluation::REFLECTED : ActionEvaluation::NOT_REFLECTED,
           null,
           false,
           null,
           $current->presentationSafe,
           $current->presentationSafe ? $current->value : null
       );
   }

   private function determinate(
        ConfiguredAction $action,
        ContextValue $current,
        int $expected,
        mixed $currentValue,
        bool $reflected
    ): ActionInspection {
       return new ActionInspection(
           $action->actionId,
           $action->actionType,
           $action->field,
           ActionSupport::SUPPORTED,
           $reflected ? ActionEvaluation::REFLECTED : ActionEvaluation::NOT_REFLECTED,
           null,
           true,
           $expected,
           $current->presentationSafe,
           $current->presentationSafe ? $currentValue : null
       );
   }

   private function indeterminate(
        ConfiguredAction $action,
        ActionSupport $support,
        string $reason
    ): ActionInspection {
       return new ActionInspection(
           $action->actionId,
           $action->actionType,
           $action->field,
           $support,
           ActionEvaluation::INDETERMINATE,
           $reason
       );
   }

   private function integerOrNull(mixed $value): ?int {
      if (is_int($value)) {
          return $value;
      }
      if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
          return (int) $value;
      }

       return null;
   }
}
