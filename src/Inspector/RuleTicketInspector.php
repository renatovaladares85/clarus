<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleTicketInspector
{
   public function __construct(
        private readonly TicketContextBuilder $contextBuilder = new TicketContextBuilder(),
        private readonly RuleTicketCandidateProvider $candidateProvider = new RuleTicketCandidateProvider(),
        private readonly RuleActionProvider $actionProvider = new RuleActionProvider(),
        private readonly RuleTicketActionAnalyzer $actionAnalyzer = new RuleTicketActionAnalyzer()
    ) {
   }

   public function inspect(
        \Ticket $ticket,
        int $condition,
        ?InspectionOptions $options = null
    ): InspectionResult {
      if (!in_array($condition, [\RuleTicket::ONADD, \RuleTicket::ONUPDATE], true)) {
          throw new \InvalidArgumentException('Inspection condition must be RuleTicket::ONADD or ONUPDATE.');
      }
      if ($ticket->isNewItem()) {
          throw new \InvalidArgumentException('Inspector requires a persisted Ticket.');
      }

       $options ??= new InspectionOptions();
       $context = $this->contextBuilder->build($ticket);
       $candidates = $this->candidateProvider->candidates($ticket, $condition);
       $candidateCount = count($candidates);
       $selected = array_slice($candidates, 0, $options->ruleLimit);
       $actionsByRule = $options->includeActions
           ? $this->actionProvider->forRuleIds(array_map(
               static fn (\RuleTicket $rule): int => NativeField::integer($rule->fields['id'] ?? 0),
               $selected
           ))
           : [];
       $rules = [];
      foreach ($selected as $rule) {
          $ruleInspection = $this->inspectRule($rule, $context, $condition);
         if ($options->includeActions) {
             $ruleInspection = $ruleInspection->withActions(
                 $this->actionAnalyzer->analyzeAll($actionsByRule[$ruleInspection->id] ?? [], $context)
             );
         }
          $rules[] = $ruleInspection;
      }

       return new InspectionResult(
           $ticket->getID(),
           $condition,
           $options->ruleLimit,
           $candidateCount,
           count($rules),
           $candidateCount > count($rules),
           $rules,
           [
               'Results describe the current reconstructable Ticket state, not historical rule execution.',
               'Configured rule actions are never executed by inspection.',
               $options->includeActions
                   ? 'Action reflection describes only the current Ticket snapshot, not historical causality.'
                   : 'Configured rule actions were not included in this inspection.',
           ]
       );
   }

   private function inspectRule(\RuleTicket $rule, TicketContext $context, int $condition): RuleInspection {
       $matchingMode = NativeField::string($rule->fields['match'] ?? '');
       $criterionResults = [];
       $limitations = [];

      if (!in_array($matchingMode, ['AND', 'OR'], true)) {
          $limitations[] = 'Rule has an unsupported native matching mode.';
          return $this->ruleResult($rule, $matchingMode, [], Evaluation::INDETERMINATE, $limitations);
      }

      if ($rule->criterias === []) {
          $limitations[] = 'Native Rule processing rejects rules without criteria.';
          return $this->ruleResult($rule, $matchingMode, [], Evaluation::INDETERMINATE, $limitations);
      }

       $nativeResults = [];
       $input = $context->availableInput();
       $diagnosticRule = clone $rule;
       $diagnosticRule->testCriterias($input, $nativeResults);

      foreach ($rule->criterias as $criterion) {
          $key = NativeField::string($criterion->fields['criteria'] ?? '');
          $contextValue = $context->get($key);
          $criterionId = NativeField::integer($criterion->fields['id'] ?? 0);

         if ($contextValue->state === ContextState::INDETERMINATE) {
             $criterionResults[] = new CriterionInspection(
                 $key,
                 NativeField::integer($criterion->fields['condition'] ?? 0),
                 NativeField::string($criterion->fields['pattern'] ?? ''),
                 Evaluation::INDETERMINATE,
                 $contextValue->reason
             );
             continue;
         }

         if (!isset($nativeResults[$criterionId]['result'])) {
             $criterionResults[] = new CriterionInspection(
                 $key,
                 NativeField::integer($criterion->fields['condition'] ?? 0),
                 NativeField::string($criterion->fields['pattern'] ?? ''),
                 Evaluation::INDETERMINATE,
                 'Native RuleTicket criterion evaluation did not return a result.'
             );
             continue;
         }

          $criterionResults[] = new CriterionInspection(
              $key,
              NativeField::integer($criterion->fields['condition'] ?? 0),
              NativeField::string($criterion->fields['pattern'] ?? ''),
              NativeField::integer($nativeResults[$criterionId]['result']) === 1
                  ? Evaluation::MATCH
                  : Evaluation::NO_MATCH,
              null,
              $contextValue->presentationSafe,
              $contextValue->presentationSafe ? $contextValue->value : null
          );
      }

       $overall = EvaluationReducer::reduce(
           $matchingMode,
           array_map(
               static fn (CriterionInspection $criterion): Evaluation => $criterion->evaluation,
               $criterionResults
           )
       );

       $allAvailable = !in_array(
           Evaluation::INDETERMINATE,
           array_map(
               static fn (CriterionInspection $criterion): Evaluation => $criterion->evaluation,
               $criterionResults
           ),
           true
       );
      if ($allAvailable) {
          $nativeRule = clone $rule;
          $overall = $nativeRule->checkCriterias($input) ? Evaluation::MATCH : Evaluation::NO_MATCH;
      }

      if ($condition === \RuleTicket::ONUPDATE) {
          $overall = Evaluation::INDETERMINATE;
          $limitations[] =
              'UPDATE eligibility depends on the original change set, which is not present on a persisted Ticket.';
      }

       return $this->ruleResult($rule, $matchingMode, $criterionResults, $overall, $limitations);
   }

    /**
     * @param list<CriterionInspection> $criteria
     * @param list<string> $limitations
     */
   private function ruleResult(
        \RuleTicket $rule,
        string $matchingMode,
        array $criteria,
        Evaluation $evaluation,
        array $limitations
    ): RuleInspection {
       return new RuleInspection(
           NativeField::integer($rule->fields['id'] ?? 0),
           NativeField::string($rule->fields['name'] ?? ''),
           NativeField::integer($rule->fields['condition'] ?? 0),
           NativeField::integer($rule->fields['entities_id'] ?? 0),
           NativeField::boolean($rule->fields['is_recursive'] ?? false),
           NativeField::integer($rule->fields['ranking'] ?? 0),
           $matchingMode,
           $criteria,
           $evaluation,
           $limitations
       );
   }
}
