<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Projects the small, deterministic RuleTicket action subset without invoking
 * GLPI action execution. Unsupported or uncertain effects taint only their
 * known criterion key, preventing later rules from being evaluated on a guess.
 */
final class RuleEffectProjector
{
   public const REASON_RULE_RESULT_INDETERMINATE = 'rule_result_indeterminate';
   public const REASON_UNSUPPORTED_ACTION = 'unsupported_action_semantics';
   public const REASON_INVALID_CONFIGURED_VALUE = 'invalid_configured_value';
   public const REASON_CATEGORY_CODE_UNAVAILABLE = 'category_code_not_reconstructible';

   /** @var array<string, list<string>> */
   private const INDIRECT_CONTEXT_KEYS = [
       '_users_id_requester' => [
           '_groups_id_of_requester',
           '_locations_id_of_requester',
           'profiles_id',
       ],
       'itilcategories_id' => ['itilcategories_id_code'],
       '_affect_itilcategory_by_code' => [
           'itilcategories_id',
           'itilcategories_id_code',
       ],
   ];

   /** @var list<string> */
   private const SCALAR_ASSIGN_FIELDS = [
       'type',
       'urgency',
       'impact',
       'priority',
       'locations_id',
       'requesttypes_id',
       'global_validation',
       'validation_percent',
   ];

   /** @var list<string> */
   private const DELETE_FIELDS = [
       'time_to_resolve',
       'time_to_own',
       'internal_time_to_resolve',
       'internal_time_to_own',
   ];

   /**
    * @param list<ConfiguredAction> $actions
    */
   public function project(Evaluation $evaluation, array $actions, TicketContext $context): RuleEffectProjection {
       $output = $context;
       $effects = [];

      foreach ($actions as $action) {
         if ($evaluation === Evaluation::NO_MATCH) {
             $effects[] = new ProjectedRuleEffect(
                 $action->actionId,
                 $action->actionType,
                 $action->field,
                 ProjectionStatus::NOT_APPLIED
             );
             continue;
         }

         if ($evaluation === Evaluation::INDETERMINATE) {
             $output = $this->taint($output, $action->field, self::REASON_RULE_RESULT_INDETERMINATE);
             $effects[] = new ProjectedRuleEffect(
                 $action->actionId,
                $action->actionType,
                $action->field,
                ProjectionStatus::INDETERMINATE,
                self::REASON_RULE_RESULT_INDETERMINATE,
                affectedFields: $this->affectedContextKeys($action->field)
             );
             continue;
         }

         [$output, $effect] = $this->apply($action, $output);
         $effects[] = $effect;
      }

       return new RuleEffectProjection($output, $effects);
   }

   /** @return array{TicketContext, ProjectedRuleEffect} */
   private function apply(ConfiguredAction $action, TicketContext $context): array {
      if ($action->actionType === 'assign' && $action->field === 'itilcategories_id') {
          return $this->assignCategory($action, $context);
      }

      if ($action->actionType === 'assign' && in_array($action->field, self::SCALAR_ASSIGN_FIELDS, true)) {
          return $this->assignInteger($action, $context);
      }

      if ($action->actionType === 'delete' && in_array($action->field, self::DELETE_FIELDS, true)) {
          $previous = $context->get($action->field);
          $output = $context->with($action->field, ContextValue::available(null, 'simulated:rule-action'));

          return [$output, new ProjectedRuleEffect(
              $action->actionId,
              $action->actionType,
              $action->field,
              ProjectionStatus::APPLIED,
              null,
              $previous->state === ContextState::AVAILABLE,
              $previous->value,
              true,
              null
          )];
      }

       $output = $this->taint($context, $action->field, self::REASON_UNSUPPORTED_ACTION);
       return [$output, new ProjectedRuleEffect(
           $action->actionId,
           $action->actionType,
           $action->field,
           ProjectionStatus::UNSUPPORTED,
           self::REASON_UNSUPPORTED_ACTION,
           affectedFields: $this->affectedContextKeys($action->field)
       )];
   }

   /** @return array{TicketContext, ProjectedRuleEffect} */
   private function assignInteger(ConfiguredAction $action, TicketContext $context): array {
       $value = $this->integerOrNull($action->configuredValue);
      if ($value === null) {
          $output = $this->taint($context, $action->field, self::REASON_INVALID_CONFIGURED_VALUE);
          return [$output, new ProjectedRuleEffect(
              $action->actionId,
              $action->actionType,
              $action->field,
              ProjectionStatus::INDETERMINATE,
              self::REASON_INVALID_CONFIGURED_VALUE
          )];
      }

       $previous = $context->get($action->field);
       $output = $context->with($action->field, ContextValue::available($value, 'simulated:rule-action', true));

       return [$output, new ProjectedRuleEffect(
           $action->actionId,
           $action->actionType,
           $action->field,
           ProjectionStatus::APPLIED,
           null,
           $previous->state === ContextState::AVAILABLE,
           $previous->value,
           true,
           $value
       )];
   }

   /** @return array{TicketContext, ProjectedRuleEffect} */
   private function assignCategory(ConfiguredAction $action, TicketContext $context): array {
       $value = $this->integerOrNull($action->configuredValue);
      if ($value === null || $value < 1) {
          $output = $this->taint($context, 'itilcategories_id', self::REASON_INVALID_CONFIGURED_VALUE)
              ->with('itilcategories_id_code', ContextValue::indeterminate(self::REASON_INVALID_CONFIGURED_VALUE));
          return [$output, new ProjectedRuleEffect(
              $action->actionId,
              $action->actionType,
              $action->field,
              ProjectionStatus::INDETERMINATE,
              self::REASON_INVALID_CONFIGURED_VALUE
          )];
      }

       $previous = $context->get('itilcategories_id');
       $output = $context->with('itilcategories_id', ContextValue::available($value, 'simulated:rule-action', true));
       $category = \ITILCategory::getById($value);
      if (!$category instanceof \ITILCategory || !array_key_exists('code', $category->fields)) {
          $output = $output->with(
              'itilcategories_id_code',
              ContextValue::indeterminate(self::REASON_CATEGORY_CODE_UNAVAILABLE)
          );
      } else {
          $output = $output->with(
              'itilcategories_id_code',
              ContextValue::available($category->fields['code'], 'simulated:category', false)
          );
      }

       return [$output, new ProjectedRuleEffect(
           $action->actionId,
           $action->actionType,
           $action->field,
           ProjectionStatus::APPLIED,
           null,
           $previous->state === ContextState::AVAILABLE,
           $previous->value,
           true,
           $value
       )];
   }

   private function taint(TicketContext $context, string $field, string $reason): TicketContext {
      foreach ($this->affectedContextKeys($field) as $contextKey) {
          $context = $context->with($contextKey, ContextValue::indeterminate($reason));
      }

       return $context;
   }

   /** @return list<string> */
   private function affectedContextKeys(string $field): array {
       return array_values(array_unique(array_merge(
           [$field],
           self::INDIRECT_CONTEXT_KEYS[$field] ?? []
       )));
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
