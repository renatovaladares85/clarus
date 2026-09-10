<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

use GlpiPlugin\Clarus\ClarusConfig;

/** Builds an escaped-by-Twig view model containing only explicitly safe values. */
final class InspectionPresenter
{
   private \Closure $entityNameResolver;

   /** @var array<int, string> */
   private array $entityNames = [];

   /** @var null|array<string, array{name: string}> */
   private ?array $criterionLabels = null;

   /** @var null|array<string, array{name: string}> */
   private ?array $actionLabels = null;

   /** @param null|callable(int): string $entityNameResolver */
   public function __construct(?callable $entityNameResolver = null) {
       $this->entityNameResolver = $entityNameResolver === null
           ? static function (int $entityId): string {
               $name = \Dropdown::getDropdownName(\Entity::getTable(), $entityId);

               return $name !== ''
                   ? $name
                   : sprintf(__('Entity #%d', 'clarus'), $entityId);
           }
           : \Closure::fromCallable($entityNameResolver);
   }

   /**
    * @param list<InspectionResult> $results
    * @param array<string, bool|int|string|list<array{field: string, direction: string}>> $settings
    * @return array<string, mixed>
    */
   public function present(
       array $results,
       array $settings,
       int $ticketId,
       string $refreshUrl,
       string $csrfToken,
       bool $loaded,
       ?string $error = null,
       bool $canViewSensitiveValues = false
   ): array {
       $rules = [];
       $candidateCount = 0;
       $evaluatedCount = 0;
       $truncated = false;
       $processingIndex = 0;
       $actionsEnabled = (bool) $settings[ClarusConfig::INCLUDE_ACTIONS];
       $counts = ['match' => 0, 'no_match' => 0, 'indeterminate' => 0];
       $entityOptions = [];
      foreach ($results as $result) {
          $candidateCount += $result->candidateCount;
          $evaluatedCount += $result->evaluatedCount;
          $truncated = $truncated || $result->truncated;
         foreach ($result->rules as $rule) {
             $rules[] = $this->rule($rule, $processingIndex++, $actionsEnabled, $canViewSensitiveValues);
             $entityOptions[$rule->entityId] = [
                 'id' => $rule->entityId,
                 'name' => $this->entityName($rule->entityId),
             ];
             ++$counts[$this->evaluationKey($rule->evaluation)];
         }
      }

       uasort($entityOptions, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

       return [
           'ticketId' => $ticketId,
           'refreshUrl' => $refreshUrl,
           'csrfToken' => $csrfToken,
           'loaded' => $loaded,
           'error' => $error,
           'truncated' => $truncated,
           'candidateCount' => $candidateCount,
           'evaluatedCount' => $evaluatedCount,
           'counts' => $counts,
           'rules' => $rules,
           'entityOptions' => array_values($entityOptions),
           'pageSize' => $settings[ClarusConfig::PAGE_SIZE],
           'initialGroup' => $settings[ClarusConfig::INITIAL_GROUP],
           'minimumAdherence' => $settings[ClarusConfig::MIN_ADHERENCE],
           'initialSort' => $this->sortLevels($settings[ClarusConfig::INITIAL_SORT]),
           'sortOptions' => $this->sortOptions(),
           'labels' => $this->labels(),
       ];
   }

   /** @return array<string, mixed> */
   private function rule(RuleInspection $rule, int $processingIndex, bool $actionsEnabled, bool $canViewSensitiveValues): array {
       $criteria = array_map(fn (CriterionInspection $criterion): array => $this->criterion($criterion, $canViewSensitiveValues), $rule->criteria);
       $matchingCriteria = count(array_filter(
           $rule->criteria,
           static fn (CriterionInspection $criterion): bool => $criterion->evaluation === Evaluation::MATCH
       ));
       $entityName = $this->entityName($rule->entityId);
       $evaluationKey = $this->evaluationKey($rule->evaluation);
       $criteriaCount = count($criteria);
       $indeterminateCriteria = count(array_filter(
           $rule->criteria,
           static fn (CriterionInspection $criterion): bool => $criterion->evaluation === Evaluation::INDETERMINATE
       ));
       $adherencePercent = $criteriaCount === 0
           ? 0
           : (int) round(($matchingCriteria / $criteriaCount) * 100, 0, PHP_ROUND_HALF_UP);

       return [
           'id' => $rule->id,
           'name' => $rule->name === '' ? __('Unnamed', 'clarus') : $rule->name,
           'condition' => $this->condition($rule->condition),
           'conditionKey' => $rule->condition === \RuleTicket::ONADD ? 'onadd' : 'onupdate',
           'entityName' => $entityName,
           'ranking' => $rule->ranking,
           'recursive' => $rule->recursive ? __('Yes') : __('No'),
           'matchingMode' => $this->matchingMode($rule->matchingMode),
           'criteria' => $criteria,
           'matchingCriteria' => $matchingCriteria,
           'criteriaCount' => $criteriaCount,
           'indeterminateCriteria' => $indeterminateCriteria,
           'adherenceNumerator' => $matchingCriteria,
           'adherenceDenominator' => $criteriaCount,
           'adherencePercent' => $adherencePercent,
           'adherenceLabel' => sprintf('%d%% (%d/%d)', $adherencePercent, $matchingCriteria, $criteriaCount),
           'actions' => array_map(fn (ActionInspection $action): array => $this->action($action, $canViewSensitiveValues), $rule->actions),
           'actionCount' => count($rule->actions),
           'actionSummary' => $actionsEnabled
               ? sprintf('%d %s', count($rule->actions), __('configured actions', 'clarus'))
               : __('Configured action analysis is disabled.', 'clarus'),
           'actionsEnabled' => $actionsEnabled,
           'evaluationKey' => $evaluationKey,
           'evaluationLabel' => $this->evaluation($rule->evaluation),
           'evaluationOrder' => ['match' => 0, 'no_match' => 1, 'indeterminate' => 2][$evaluationKey],
           'processingIndex' => $processingIndex,
           'entitySort' => mb_strtolower($entityName, 'UTF-8'),
           'entityId' => $rule->entityId,
           'searchText' => mb_strtolower($rule->id . ' ' . $rule->name, 'UTF-8'),
           'limitations' => array_map(fn (string $reason): string => $this->limitation($reason), $rule->limitations),
       ];
   }

   /**
    * @param mixed $sort
   * @return list<array{field: string, direction: string}>
    */
   private function sortLevels(mixed $sort): array {
       $levels = [];
      if (is_array($sort)) {
         foreach ($sort as $level) {
            if (is_array($level) && isset($level['field'], $level['direction'])
                && is_string($level['field']) && is_string($level['direction'])) {
                $levels[] = ['field' => $level['field'], 'direction' => $level['direction']];
            }
         }
      }
      while (count($levels) < ClarusConfig::MAX_SORT_LEVELS) {
          $levels[] = ['field' => '', 'direction' => 'asc'];
      }

       return $levels;
   }

   /** @return array<string, string> */
   private function sortOptions(): array {
       return [
           'result' => __('Result', 'clarus'),
           'adherence' => __('Confirmed adherence', 'clarus'),
           'matches' => __('Matching criteria', 'clarus'),
           'criteria' => __('Configured criteria', 'clarus'),
           'indeterminate' => __('Indeterminate criteria', 'clarus'),
           'ranking' => __('Ranking', 'clarus'),
           'entity' => __('Entity', 'clarus'),
           'condition' => __('Condition', 'clarus'),
           'actions' => __('Configured actions', 'clarus'),
           'name' => __('Rule name', 'clarus'),
           'id' => __('Rule ID', 'clarus'),
       ];
   }

   /** @return array<string, mixed> */
   private function criterion(CriterionInspection $criterion, bool $canViewSensitiveValues): array {
       return [
           'name' => $this->criterionName($criterion->key),
           'operator' => $this->operator($criterion->operator, $criterion->key),
           'state' => $this->criterionState($criterion->evaluation),
           'evaluationKey' => $this->evaluationKey($criterion->evaluation),
           'expected' => $criterion->expectedValuePresentationSafe
               && (TicketContextBuilder::isPresentationSafeKey($criterion->key) || $canViewSensitiveValues)
               ? $this->safeValue($criterion->pattern)
               : __('Hidden for safety', 'clarus'),
           'observed' => $criterion->hasObservedValue
               && (TicketContextBuilder::isPresentationSafeKey($criterion->key) || $canViewSensitiveValues)
               ? $this->safeValue($criterion->observedValue)
               : __('Hidden or unavailable', 'clarus'),
           'limitation' => $criterion->reason === null ? null : $this->limitation($criterion->reason),
       ];
   }

   /** @return array<string, mixed> */
   private function action(ActionInspection $action, bool $canViewSensitiveValues): array {
       return [
           'type' => $this->actionType($action->actionType),
           'field' => $this->actionField($action->field),
           'support' => $this->actionSupport($action->support),
           'evaluation' => $this->actionEvaluation($action->evaluation),
           'configured' => $action->configuredValuePresentationSafe && $canViewSensitiveValues
               ? $this->safeActionValue($action->configuredValue)
               : __('Hidden for safety', 'clarus'),
           'current' => $action->currentValuePresentationSafe && $canViewSensitiveValues
               ? $this->safeActionValue($action->currentValue)
               : __('Hidden or unavailable', 'clarus'),
           'limitation' => $action->reason === null ? null : $this->limitation($action->reason),
       ];
   }

   /** @return array<string, string> */
   private function labels(): array {
       return [
           'title' => __('Rule inspection', 'clarus'),
           'description' => __('Current-state diagnostic of the last saved Ticket data only. No rule is executed or changed.', 'clarus'),
           'persistedState' => __('Unsaved form changes are not included in this diagnostic.', 'clarus'),
           'refresh' => __('Refresh inspection', 'clarus'),
           'runInspection' => __('Run inspection', 'clarus'),
           'refreshing' => __('Refreshing inspection...', 'clarus'),
           'evaluated' => __('Evaluated', 'clarus'),
           'matches' => __('Matches', 'clarus'),
           'matchingRules' => __('Matching rules', 'clarus'),
           'doesNotMatch' => __('Does not match', 'clarus'),
           'nonMatchingRules' => __('Non-matching rules', 'clarus'),
           'indeterminate' => __('Not evaluated', 'clarus'),
           'searchAndFilter' => __('Search and filter', 'clarus'),
           'searchPlaceholder' => __('Search by rule name or ID', 'clarus'),
           'all' => __('All', 'clarus'),
           'groupProcessing' => __('Processing order', 'clarus'),
           'groupResult' => __('Result', 'clarus'),
           'groupEntity' => __('Entity', 'clarus'),
           'grouping' => __('Grouping', 'clarus'),
           'resultFilter' => __('Result filters', 'clarus'),
           'conditionFilter' => __('Condition filters', 'clarus'),
           'entityFilter' => __('Entity filters', 'clarus'),
           'onadd' => __('On ticket creation (ONADD)', 'clarus'),
           'onupdate' => __('On ticket update (ONUPDATE)', 'clarus'),
           'minimumAdherence' => __('Minimum confirmed adherence', 'clarus'),
           'minimumAdherenceHint' => __('Enter a value from 0% to 100%. For example: 80%.', 'clarus'),
           'sortBy' => __('Sort by', 'clarus'),
           'sortLevel' => __('Level %d', 'clarus'),
           'noSort' => __('No additional sort', 'clarus'),
           'ascending' => __('Ascending', 'clarus'),
           'descending' => __('Descending', 'clarus'),
           'results' => __('Results', 'clarus'),
           'criteria' => __('Criteria', 'clarus'),
           'criterion' => __('Criterion', 'clarus'),
           'operator' => __('Operator', 'clarus'),
           'expected' => __('Expected', 'clarus'),
           'observed' => __('Observed', 'clarus'),
           'limitation' => __('Limitation', 'clarus'),
           'configuredActions' => __('Configured actions', 'clarus'),
           'action' => __('Action', 'clarus'),
           'field' => __('Field', 'clarus'),
           'configuredValue' => __('Configured value', 'clarus'),
           'currentValue' => __('Current value', 'clarus'),
           'support' => __('Support', 'clarus'),
           'reflection' => __('Current-state reflection', 'clarus'),
           'noActionsExecuted' => __('No configured action was executed.', 'clarus'),
           'actionReflectionNotice' => __(
               'Current-state reflection does not prove historical execution or causality.',
               'clarus'
           ),
           'actionAnalysisDisabled' => __('Configured action analysis is disabled.', 'clarus'),
           'ranking' => __('Ranking', 'clarus'),
           'condition' => __('Condition', 'clarus'),
           'entity' => __('Entity', 'clarus'),
           'criteriaMatch' => __('criteria match', 'clarus'),
           'confirmedAdherence' => __('Confirmed adherence', 'clarus'),
           'indeterminateCriteria' => __('Indeterminate criteria', 'clarus'),
           'cannotSafelyEvaluate' => __('Clarus could not evaluate one or more criteria safely using the data available in the Ticket.', 'clarus'),
           'empty' => __('No rules were found for the enabled conditions.', 'clarus'),
           'notLoaded' => __('Automatic inspection is disabled. Run an inspection to load current results.', 'clarus'),
           'truncated' => __('Results were truncated at the configured rule limit.', 'clarus'),
           'technicalError' => __('The inspection could not be completed. Try again or contact an administrator.', 'clarus'),
           'showing' => __('Showing', 'clarus'),
           'of' => __('of', 'clarus'),
           'rules' => __('rules', 'clarus'),
           'noFilteredResults' => __('No rules match the current search and filters.', 'clarus'),
           'previous' => __('Previous page', 'clarus'),
           'next' => __('Next page', 'clarus'),
       ];
   }

   private function evaluationKey(Evaluation $evaluation): string {
       return match ($evaluation) {
           Evaluation::MATCH => 'match',
           Evaluation::NO_MATCH => 'no_match',
           Evaluation::INDETERMINATE => 'indeterminate',
       };
   }

   private function evaluation(Evaluation $evaluation): string {
       return match ($evaluation) {
           Evaluation::MATCH => __('Matches', 'clarus'),
           Evaluation::NO_MATCH => __('Does not match', 'clarus'),
           Evaluation::INDETERMINATE => __('Not evaluated', 'clarus'),
       };
   }

   private function criterionState(Evaluation $evaluation): string {
       return match ($evaluation) {
           Evaluation::MATCH => 'PASS',
           Evaluation::NO_MATCH => 'FAIL',
           Evaluation::INDETERMINATE => 'UNKNOWN',
       };
   }

   private function actionEvaluation(ActionEvaluation $evaluation): string {
       return match ($evaluation) {
           ActionEvaluation::REFLECTED => __('Reflected in current state', 'clarus'),
           ActionEvaluation::NOT_REFLECTED => __('Not reflected in current state', 'clarus'),
           ActionEvaluation::INDETERMINATE => __('Indeterminate', 'clarus'),
       };
   }

   private function actionSupport(ActionSupport $support): string {
       return match ($support) {
           ActionSupport::SUPPORTED => __('Supported', 'clarus'),
           ActionSupport::INDETERMINATE_BY_DESIGN => __('Indeterminate by design', 'clarus'),
           ActionSupport::UNSUPPORTED => __('Unsupported', 'clarus'),
       };
   }

   private function condition(int $condition): string {
       return match ($condition) {
           \RuleTicket::ONADD => __('On ticket creation (ONADD)', 'clarus'),
           \RuleTicket::ONUPDATE => __('On ticket update (ONUPDATE)', 'clarus'),
           default => sprintf(__('Unknown condition (%d)', 'clarus'), $condition),
       };
   }

   private function matchingMode(string $matchingMode): string {
       return match ($matchingMode) {
           'AND' => __('All criteria (AND)', 'clarus'),
           'OR' => __('Any criterion (OR)', 'clarus'),
           default => sprintf(__('Unknown matching mode (%s)', 'clarus'), $matchingMode),
       };
   }

   private function criterionName(string $key): string {
       $this->criterionLabels ??= (new \RuleTicket())->getCriterias();
       $label = $this->criterionLabels[$key]['name'] ?? null;

       return is_string($label) ? $label : sprintf(__('Unknown criterion (%s)', 'clarus'), $key);
   }

   private function operator(int $operator, string $criterion): string {
       $label = \RuleCriteria::getConditionByID($operator, \RuleTicket::class, $criterion);

       return $label !== '' ? $label : sprintf(__('Unknown operator (%d)', 'clarus'), $operator);
   }

   private function actionType(string $actionType): string {
       $label = \RuleAction::getActionByID($actionType);

       return $label !== '' ? $label : sprintf(__('Unknown action (%s)', 'clarus'), $actionType);
   }

   private function actionField(string $field): string {
       $this->actionLabels ??= (new \RuleTicket())->getActions();
       $label = $this->actionLabels[$field]['name'] ?? null;

       return is_string($label) ? $label : sprintf(__('Unknown field (%s)', 'clarus'), $field);
   }

   private function entityName(int $entityId): string {
      if (!array_key_exists($entityId, $this->entityNames)) {
          $resolved = ($this->entityNameResolver)($entityId);
          $this->entityNames[$entityId] = is_string($resolved)
              ? $resolved
              : sprintf(__('Entity #%d', 'clarus'), $entityId);
      }

       return $this->entityNames[$entityId];
   }

   private function safeValue(mixed $value): string {
      if ($value === null) {
          return __('Not set', 'clarus');
      }
      if (is_bool($value)) {
          return $value ? __('Yes') : __('No');
      }
      if (is_int($value) || is_float($value) || is_string($value)) {
          return mb_strimwidth((string) $value, 0, 200, '…', 'UTF-8');
      }
      if (is_array($value)) {
          $scalars = [];
         foreach ($value as $item) {
            if (!is_int($item) && !is_float($item) && !is_string($item)) {
                return __('Omitted for safety', 'clarus');
            }
             $scalars[] = (string) $item;
         }

          return mb_strimwidth(implode(', ', $scalars), 0, 200, '…', 'UTF-8');
      }

       return __('Omitted for safety', 'clarus');
   }

   private function safeActionValue(mixed $value): string {
      if ($value === null || is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
          return $this->safeValue($value);
      }
      if (is_array($value)) {
         foreach ($value as $item) {
            if (!is_int($item) && (!is_string($item) || preg_match('/^-?\d+$/D', $item) !== 1)) {
                return __('Omitted for safety', 'clarus');
            }
         }

          return $this->safeValue($value);
      }

       return __('Omitted for safety', 'clarus');
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
           default => __('A diagnostic limitation applies to this item.', 'clarus'),
       };
   }
}
