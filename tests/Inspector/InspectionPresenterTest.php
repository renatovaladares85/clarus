<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\ClarusConfig;
use GlpiPlugin\Clarus\Inspector\ActionEvaluation;
use GlpiPlugin\Clarus\Inspector\ActionInspection;
use GlpiPlugin\Clarus\Inspector\ActionSupport;
use GlpiPlugin\Clarus\Inspector\CriterionInspection;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionPresenter;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use PHPUnit\Framework\TestCase;

final class InspectionPresenterTest extends TestCase
{
   public function testPresentsExactlyThreeResultsAndCriterionProportions(): void {
       $rules = [
           $this->rule(1, Evaluation::MATCH, [Evaluation::MATCH, Evaluation::MATCH]),
           $this->rule(2, Evaluation::NO_MATCH, [Evaluation::MATCH, Evaluation::NO_MATCH]),
           $this->rule(3, Evaluation::INDETERMINATE, [Evaluation::INDETERMINATE]),
       ];
       $result = new InspectionResult(12, \RuleTicket::ONADD, 1000, 3, 3, false, $rules);

       $view = $this->presenter()->present(
           [$result],
           ClarusConfig::defaults(),
           12,
           '/refresh',
           'token',
           true
       );

       $counts = $view['counts'];
       $presentedRules = $view['rules'];
       self::assertIsArray($counts);
       self::assertIsArray($presentedRules);
       self::assertSame(['match' => 1, 'no_match' => 1, 'indeterminate' => 1], $counts);
       self::assertCount(3, $presentedRules);
       $secondRule = $presentedRules[1];
       self::assertIsArray($secondRule);
       self::assertSame(1, $secondRule['matchingCriteria']);
       self::assertSame(2, $secondRule['criteriaCount']);
       self::assertSame(0, $secondRule['indeterminateCriteria']);
       self::assertSame(50, $secondRule['adherencePercent']);
       self::assertSame(1, $secondRule['adherenceNumerator']);
       self::assertSame(2, $secondRule['adherenceDenominator']);
       self::assertSame('50% (1/2)', $secondRule['adherenceLabel']);
       $evaluationKeys = [];
      foreach ($presentedRules as $presentedRule) {
          self::assertIsArray($presentedRule);
          $evaluationKeys[] = $presentedRule['evaluationKey'];
      }
       self::assertSame(['match', 'no_match', 'indeterminate'], $evaluationKeys);
       self::assertArrayNotHasKey('partial', $counts);
   }

   public function testAdherenceIncludesIndeterminateCriteriaAndKeepsSemanticResultIndependent(): void {
       $rules = [
           $this->rule(1, Evaluation::MATCH, [Evaluation::MATCH, Evaluation::MATCH, Evaluation::MATCH, Evaluation::MATCH, Evaluation::INDETERMINATE]),
           $this->rule(2, Evaluation::MATCH, [Evaluation::MATCH, Evaluation::NO_MATCH, Evaluation::NO_MATCH]),
           $this->rule(3, Evaluation::INDETERMINATE, []),
       ];
       $result = new InspectionResult(12, \RuleTicket::ONADD, 1000, 3, 3, false, $rules);

       $view = $this->presenter()->present([$result], ClarusConfig::defaults(), 12, '/refresh', 'token', true);
       $presented = $view['rules'];

       self::assertIsArray($presented);
       self::assertIsArray($presented[0]);
       self::assertIsArray($presented[1]);
       self::assertIsArray($presented[2]);
       self::assertSame('80% (4/5)', $presented[0]['adherenceLabel']);
       self::assertSame(4, $presented[0]['adherenceNumerator']);
       self::assertSame(5, $presented[0]['adherenceDenominator']);
       self::assertSame(1, $presented[0]['indeterminateCriteria']);
       self::assertSame(Evaluation::MATCH, $rules[1]->evaluation);
       self::assertSame('33% (1/3)', $presented[1]['adherenceLabel']);
       self::assertSame('0% (0/0)', $presented[2]['adherenceLabel']);
       self::assertSame('indeterminate', $presented[2]['evaluationKey']);
   }

   public function testOnlyExplicitlySafeValuesReachTheViewModel(): void {
       $safe = new CriterionInspection('urgency', 2, '3', Evaluation::MATCH, null, true, 3, true);
       $unsafe = new CriterionInspection(
           'content',
           2,
           'secret-pattern',
           Evaluation::MATCH,
           null,
           true,
           'secret-observed',
           true
       );
       $safeAction = new ActionInspection(
           1,
           'assign',
           'urgency',
           ActionSupport::SUPPORTED,
           ActionEvaluation::REFLECTED,
           null,
           true,
           3,
           true,
           3
       );
       $defensiveAction = new ActionInspection(
           2,
           'assign',
           'urgency',
           ActionSupport::SUPPORTED,
           ActionEvaluation::REFLECTED,
           null,
           true,
           'secret-action',
           true,
           'secret-current'
       );
       $rule = new RuleInspection(
           1,
           'Rule',
           \RuleTicket::ONADD,
           9,
           false,
           1,
           'AND',
           [$safe, $unsafe],
           Evaluation::MATCH,
           ['raw stack /srv/glpi'],
           [$safeAction, $defensiveAction]
       );
       $result = new InspectionResult(12, \RuleTicket::ONADD, 1000, 1, 1, false, [$rule]);

       $view = $this->presenter()->present(
           [$result],
           ClarusConfig::defaults(),
           12,
           '/refresh',
           'token',
           true
       );
       $presentedRules = $view['rules'];
       self::assertIsArray($presentedRules);
       $presented = $presentedRules[0];
       self::assertIsArray($presented);
       $criteria = $presented['criteria'];
       $actions = $presented['actions'];
       $limitations = $presented['limitations'];
       self::assertIsArray($criteria);
       self::assertIsArray($actions);
       self::assertIsArray($limitations);
       self::assertIsArray($criteria[0]);
       self::assertIsArray($criteria[1]);
       self::assertIsArray($actions[0]);
       self::assertIsArray($actions[1]);

       self::assertSame('3', $criteria[0]['expected']);
       self::assertSame('3', $criteria[0]['observed']);
       self::assertSame('Omitted for safety', $criteria[1]['expected']);
       self::assertSame('Omitted or unavailable', $criteria[1]['observed']);
       self::assertSame('3', $actions[0]['configured']);
       self::assertSame('Omitted for safety', $actions[1]['configured']);
       self::assertSame('A diagnostic limitation applies to this item.', $limitations[0]);
       self::assertSame('Entity 9', $presented['entityName']);
   }

   public function testPresentationKeepsEveryEvaluatedRuleIndependentOfPageSize(): void {
       $rules = [];
      for ($id = 1; $id <= 30; ++$id) {
          $rules[] = $this->rule($id, Evaluation::MATCH, [Evaluation::MATCH]);
      }
       $result = new InspectionResult(12, \RuleTicket::ONADD, 1000, 30, 30, false, $rules);
       $settings = ClarusConfig::defaults();
       $settings[ClarusConfig::PAGE_SIZE] = 10;

       $view = $this->presenter()->present([$result], $settings, 12, '/refresh', 'token', true);

       self::assertSame(10, $view['pageSize']);
       self::assertSame(0, $view['minimumAdherence']);
       $initialSort = $view['initialSort'];
       self::assertIsArray($initialSort);
       self::assertCount(3, $initialSort);
       $presentedRules = $view['rules'];
       self::assertIsArray($presentedRules);
       self::assertCount(30, $presentedRules);
   }

   private function presenter(): InspectionPresenter {
       return new InspectionPresenter(static fn (int $id): string => 'Entity ' . $id);
   }

   /** @param list<Evaluation> $evaluations */
   private function rule(int $id, Evaluation $evaluation, array $evaluations): RuleInspection {
       $criteria = [];
      foreach ($evaluations as $criterionEvaluation) {
          $criteria[] = new CriterionInspection('urgency', 2, '3', $criterionEvaluation, null, true, 3, true);
      }

       return new RuleInspection(
           $id,
           'Rule ' . $id,
           \RuleTicket::ONADD,
           0,
           true,
           $id,
           'AND',
           $criteria,
           $evaluation
       );
   }
}
