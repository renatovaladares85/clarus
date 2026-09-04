<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Integration\Inspection;

use GlpiPlugin\Clarus\Inspector\ActionEvaluation;
use GlpiPlugin\Clarus\Inspector\ActionInspection;
use GlpiPlugin\Clarus\Inspector\ActionSupport;
use GlpiPlugin\Clarus\Inspector\ContextState;
use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use GlpiPlugin\Clarus\Inspector\RuleActionProvider;
use GlpiPlugin\Clarus\Inspector\RuleTicketCandidateProvider;
use GlpiPlugin\Clarus\Inspector\RuleTicketInspector;
use GlpiPlugin\Clarus\Inspector\TicketContextBuilder;
use PHPUnit\Framework\TestCase;

/** @group glpi-integration */
final class RuleTicketInspectorTest extends TestCase
{
    /** @var array<string, array<int, true>> */
   private static array $pendingIds = [
        'tickets' => [],
        'actions' => [],
        'criteria' => [],
        'rules' => [],
        'slas' => [],
        'olas' => [],
        'slms' => [],
        'suppliers' => [],
        'groups' => [],
        'categories' => [],
        'entities' => [],
    ];

    /** @var array<string, array<int, true>> */
   private array $createdIds = [
        'tickets' => [],
        'actions' => [],
        'criteria' => [],
        'rules' => [],
        'slas' => [],
        'olas' => [],
        'slms' => [],
        'suppliers' => [],
        'groups' => [],
        'categories' => [],
        'entities' => [],
    ];

   private string $prefix;

   protected function setUp(): void {
       parent::setUp();
       $this->prefix = 'clarus-phase3-' . str_replace('.', '', uniqid('', true));
   }

   protected function tearDown(): void {
       $errors = self::cleanup($this->createdIds);
       $this->createdIds = self::emptyIdMap();
       parent::tearDown();

      if ($errors !== []) {
          self::fail('RuleTicket inspector cleanup failed: ' . implode('; ', $errors));
      }
   }

   public static function tearDownAfterClass(): void {
       $errors = self::cleanup(self::$pendingIds);
      if ($errors !== []) {
          throw new \RuntimeException('RuleTicket inspector fallback cleanup failed: ' . implode('; ', $errors));
      }
   }

   public function testContextReconstructsPersistedFieldsAndActors(): void {
       $categoryId = $this->createCategory('CTX');
       $ticket = $this->createTicket(0, $categoryId);
       $context = (new TicketContextBuilder())->build($ticket);
       $requesterId = (int) \Session::getLoginUserID();

       self::assertSame(ContextState::AVAILABLE, $context->get('name')->state);
       self::assertSame($this->prefix, $context->get('name')->value);
       self::assertSame(0, (int) $context->get('entities_id')->value);
       self::assertSame($categoryId, (int) $context->get('itilcategories_id')->value);
       self::assertSame('CTX', $context->get('itilcategories_id_code')->value);
       self::assertSame(ContextState::AVAILABLE, $context->get('_users_id_requester')->state);
       self::assertContains($requesterId, $context->get('_users_id_requester')->value);
       self::assertSame(ContextState::AVAILABLE, $context->get('_groups_id_of_requester')->state);
       self::assertSame(ContextState::AVAILABLE, $context->get('_locations_id_of_requester')->state);
       self::assertSame(ContextState::AVAILABLE, $context->get('profiles_id')->state);
       self::assertSame(ContextState::INDETERMINATE, $context->get('_from')->state);
   }

   public function testCandidateSelectionPreservesEntityRecursionConditionAndNativeOrder(): void {
       $parentId = $this->createEntity($this->prefix . '-parent', 0);
       $childId = $this->createEntity($this->prefix . '-child', $parentId);
       $ticket = $this->createTicket($childId);
       $parentRecursive = $this->createRule(
           'parent-recursive',
           \RuleTicket::ONADD,
           true,
           90,
           [],
           [],
           $parentId,
           true
       );
       $parentFlat = $this->createRule(
           'parent-flat',
           \RuleTicket::ONADD,
           true,
           1,
           [],
           [],
           $parentId,
           false
       );
       $inactive = $this->createRule('inactive-child', \RuleTicket::ONADD, false, 1, [], [], $childId);
       $update = $this->createRule('update-child', \RuleTicket::ONUPDATE, true, 2, [], [], $childId);
       $child = $this->createRule('child', \RuleTicket::ONADD, true, 1, [], [], $childId);

       $candidateIds = array_map(
           static fn (\RuleTicket $rule): int => (int) $rule->getID(),
           (new RuleTicketCandidateProvider())->candidates($ticket, \RuleTicket::ONADD)
       );

       self::assertContains($parentRecursive->getID(), $candidateIds);
       self::assertContains($child->getID(), $candidateIds);
       self::assertNotContains($parentFlat->getID(), $candidateIds);
       self::assertNotContains($inactive->getID(), $candidateIds);
       self::assertNotContains($update->getID(), $candidateIds);
       self::assertLessThan(
           array_search($child->getID(), $candidateIds, true),
           array_search($parentRecursive->getID(), $candidateIds, true)
       );
   }

   public function testCandidateSelectionAndEvaluationRemainReadOnly(): void {
       $ticket = $this->createTicket();
       $matching = $this->createRule('matching', \RuleTicket::ONADD, true, 10, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [['assign', 'urgency', '5']]);
       $inactive = $this->createRule('inactive', \RuleTicket::ONADD, false, 1);
       $update = $this->createRule('update', \RuleTicket::ONUPDATE, true, 2);

       $provider = new RuleTicketCandidateProvider();
       $candidateIds = array_map(
           static fn (\RuleTicket $rule): int => (int) $rule->getID(),
           $provider->candidates($ticket, \RuleTicket::ONADD)
       );
       self::assertContains($matching->getID(), $candidateIds);
       self::assertNotContains($inactive->getID(), $candidateIds);
       self::assertNotContains($update->getID(), $candidateIds);

       $before = $this->snapshot($ticket->getID(), $matching->getID());
       $result = (new RuleTicketInspector())->inspect($ticket, \RuleTicket::ONADD);
       $after = $this->snapshot($ticket->getID(), $matching->getID());
       $matchingResult = $this->findRule($result->rules, $matching->getID());

       self::assertSame(Evaluation::MATCH, $matchingResult->evaluation);
       self::assertSame([], $matchingResult->actions);
       self::assertSame($before, $after, 'Inspection must not change Ticket or Rule persistence.');
       self::assertSame('3', (string) $after['ticket']['urgency']);
   }

   public function testUnavailableHistoricalDataAndUpdateChangeSetAreIndeterminate(): void {
       $ticket = $this->createTicket();
       $mailRule = $this->createRule('mail', \RuleTicket::ONADD, true, 1, [
           ['_from', \Rule::PATTERN_IS, 'sender@example.test'],
       ]);
       $updateRule = $this->createRule('update-known-value', \RuleTicket::ONUPDATE, true, 2, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ]);

       $addResult = (new RuleTicketInspector())->inspect($ticket, \RuleTicket::ONADD);
       $mailResult = $this->findRule($addResult->rules, $mailRule->getID());
       self::assertSame(Evaluation::INDETERMINATE, $mailResult->evaluation);
       self::assertSame(Evaluation::INDETERMINATE, $mailResult->criteria[0]->evaluation);

       $updateResult = (new RuleTicketInspector())->inspect($ticket, \RuleTicket::ONUPDATE);
       $inspectedUpdate = $this->findRule($updateResult->rules, $updateRule->getID());
       self::assertSame(Evaluation::INDETERMINATE, $inspectedUpdate->evaluation);
       self::assertNotSame([], $inspectedUpdate->limitations);
   }

   public function testNativeEvaluationCoversNoMatchThreeStateTreeAndArray(): void {
       $parentId = $this->createEntity($this->prefix . '-tree-parent', 0);
       $childId = $this->createEntity($this->prefix . '-tree-child', $parentId);
       $ticket = $this->createTicket($childId);
       $requesterId = (int) \Session::getLoginUserID();
       $noMatch = $this->createRule('no-match', \RuleTicket::ONADD, true, 1, [
           ['name', \Rule::PATTERN_IS, 'different'],
       ], [], $childId);
       $andIndeterminate = $this->createRule('and-indeterminate', \RuleTicket::ONADD, true, 2, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
           ['_from', \Rule::PATTERN_IS, 'sender@example.test'],
       ], [], $childId);
       $andNoMatch = $this->createRule('and-no-match', \RuleTicket::ONADD, true, 3, [
           ['name', \Rule::PATTERN_IS, 'different'],
           ['_from', \Rule::PATTERN_IS, 'sender@example.test'],
       ], [], $childId);
       $orMatch = $this->createRule('or-match', \RuleTicket::ONADD, true, 4, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
           ['_from', \Rule::PATTERN_IS, 'sender@example.test'],
       ], [], $childId, true, \Rule::OR_MATCHING);
       $orIndeterminate = $this->createRule('or-indeterminate', \RuleTicket::ONADD, true, 5, [
           ['name', \Rule::PATTERN_IS, 'different'],
           ['_from', \Rule::PATTERN_IS, 'sender@example.test'],
       ], [], $childId, true, \Rule::OR_MATCHING);
       $tree = $this->createRule('tree', \RuleTicket::ONADD, true, 6, [
           ['entities_id', \Rule::PATTERN_UNDER, (string) $parentId],
       ], [], $childId);
       $array = $this->createRule('array', \RuleTicket::ONADD, true, 7, [
           ['_users_id_requester', \Rule::PATTERN_IS, (string) $requesterId],
       ], [], $childId);

       $result = (new RuleTicketInspector())->inspect($ticket, \RuleTicket::ONADD);

       self::assertSame(Evaluation::NO_MATCH, $this->findRule($result->rules, $noMatch->getID())->evaluation);
       self::assertSame(
           Evaluation::INDETERMINATE,
           $this->findRule($result->rules, $andIndeterminate->getID())->evaluation
       );
       self::assertSame(
           Evaluation::NO_MATCH,
           $this->findRule($result->rules, $andNoMatch->getID())->evaluation
       );
       self::assertSame(Evaluation::MATCH, $this->findRule($result->rules, $orMatch->getID())->evaluation);
       self::assertSame(
           Evaluation::INDETERMINATE,
           $this->findRule($result->rules, $orIndeterminate->getID())->evaluation
       );
       self::assertSame(Evaluation::MATCH, $this->findRule($result->rules, $tree->getID())->evaluation);
       self::assertSame(Evaluation::MATCH, $this->findRule($result->rules, $array->getID())->evaluation);
   }

   public function testLimitReportsCandidateCountAndTruncation(): void {
       $ticket = $this->createTicket();
       $this->createRule('limit-a', \RuleTicket::ONADD, true, 1, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ]);
       $this->createRule('limit-b', \RuleTicket::ONADD, true, 2, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ]);

       $result = (new RuleTicketInspector())->inspect(
           $ticket,
           \RuleTicket::ONADD,
           new InspectionOptions(1)
       );

       self::assertGreaterThanOrEqual(2, $result->candidateCount);
       self::assertSame(1, $result->evaluatedCount);
       self::assertSame(1, $result->configuredLimit);
       self::assertTrue($result->truncated);
   }

   public function testRuleWithoutCriteriaIsReportedAsIndeterminate(): void {
       $ticket = $this->createTicket();
       $empty = $this->createRule('empty', \RuleTicket::ONADD, true, 1);

       $result = (new RuleTicketInspector())->inspect($ticket, \RuleTicket::ONADD);
       $inspected = $this->findRule($result->rules, $empty->getID());

       self::assertSame(Evaluation::INDETERMINATE, $inspected->evaluation);
       self::assertNotSame([], $inspected->limitations);
   }

   public function testActionProviderBatchesSelectedRulesInNativeOrder(): void {
       $first = $this->createRule('provider-first', \RuleTicket::ONADD, true, 1, [], [
           ['assign', 'urgency', '4'],
           ['assign', 'impact', '5'],
       ]);
       $excluded = $this->createRule('provider-excluded', \RuleTicket::ONADD, true, 2, [], [
           ['assign', 'status', '2'],
       ]);

       $provided = (new RuleActionProvider())->forRuleIds([$first->getID()]);
       $native = (new \RuleAction())->getRuleActions($first->getID());

       self::assertSame([$first->getID()], array_keys($provided));
       self::assertArrayNotHasKey($excluded->getID(), $provided);
       self::assertSame(
           array_map(static fn (\RuleAction $action): int => (int) $action->fields['id'], $native),
           array_map(static fn ($action): int => $action->actionId, $provided[$first->getID()])
       );
       self::assertSame([0, 1], array_map(static fn ($action): int => $action->order, $provided[$first->getID()]));
   }

   public function testSupportedScalarRelationAndDeleteActionsRemainReadOnly(): void {
       $categoryId = $this->createCategory('ACTION');
       $ticket = $this->createTicket(0, $categoryId);
       $requesterId = (int) \Session::getLoginUserID();
       $rule = $this->createRule('supported-actions', \RuleTicket::ONADD, true, 1, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [
           ['assign', 'urgency', '3'],
           ['assign', 'impact', '5'],
           ['assign', 'itilcategories_id', (string) $categoryId],
           ['assign', '_users_id_requester', (string) $requesterId],
           ['assign', '_users_id_assign', (string) $requesterId],
           ['delete', 'time_to_resolve', '1'],
       ]);

       $before = $this->snapshot($ticket->getID(), $rule->getID());
       $result = (new RuleTicketInspector())->inspect(
           $ticket,
           \RuleTicket::ONADD,
           new InspectionOptions(1000, true)
       );
       $after = $this->snapshot($ticket->getID(), $rule->getID());
       $inspected = $this->findRule($result->rules, $rule->getID());

       self::assertSame(ActionEvaluation::REFLECTED, $this->findAction($inspected->actions, 'urgency')->evaluation);
       self::assertSame(ActionEvaluation::NOT_REFLECTED, $this->findAction($inspected->actions, 'impact')->evaluation);
       self::assertSame(
           ActionEvaluation::REFLECTED,
           $this->findAction($inspected->actions, 'itilcategories_id')->evaluation
       );
       self::assertSame(
           ActionEvaluation::REFLECTED,
           $this->findAction($inspected->actions, '_users_id_requester')->evaluation
       );
       self::assertSame(
           ActionEvaluation::NOT_REFLECTED,
           $this->findAction($inspected->actions, '_users_id_assign')->evaluation
       );
       self::assertSame(
           ActionEvaluation::REFLECTED,
           $this->findAction($inspected->actions, 'time_to_resolve')->evaluation
       );
       self::assertSame($before, $after, 'Action inspection must not change persistence or actor relations.');
   }

   public function testAllSupportedActorRelationsUseCurrentMembership(): void {
       $groupId = $this->createGroup();
       $supplierId = $this->createSupplier();
       $userId = (int) \Session::getLoginUserID();
       $ticket = $this->createTicket(0, 0, [
           '_actors' => [
               'requester' => [
                   ['itemtype' => 'User', 'items_id' => $userId],
                   ['itemtype' => 'Group', 'items_id' => $groupId],
               ],
               'assign' => [
                   ['itemtype' => 'User', 'items_id' => $userId],
                   ['itemtype' => 'Group', 'items_id' => $groupId],
                   ['itemtype' => 'Supplier', 'items_id' => $supplierId],
               ],
               'observer' => [
                   ['itemtype' => 'User', 'items_id' => $userId],
                   ['itemtype' => 'Group', 'items_id' => $groupId],
               ],
           ],
       ]);
       $rule = $this->createRule('actor-actions', \RuleTicket::ONADD, true, 1, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [
           ['assign', '_users_id_requester', (string) $userId],
           ['append', '_groups_id_requester', (string) $groupId],
           ['append', '_users_id_assign', (string) $userId],
           ['assign', '_groups_id_assign', (string) $groupId],
           ['append', '_suppliers_id_assign', (string) $supplierId],
           ['assign', '_users_id_observer', (string) $userId],
           ['append', '_groups_id_observer', (string) $groupId],
       ]);

       $inspected = $this->findRule(
           (new RuleTicketInspector())->inspect(
               $ticket,
               \RuleTicket::ONADD,
               new InspectionOptions(1000, true)
           )->rules,
           $rule->getID()
       );

       self::assertCount(7, $inspected->actions);
      foreach ($inspected->actions as $action) {
          self::assertSame(ActionSupport::SUPPORTED, $action->support);
          self::assertSame(ActionEvaluation::REFLECTED, $action->evaluation);
      }
   }

   public function testSlaAndOlaIdsAreComparedWithoutClaimingDerivedDates(): void {
       $slmId = $this->createSlm();
       $slaId = $this->createAgreement(\SLA::class, 'slas', $slmId);
       $olaId = $this->createAgreement(\OLA::class, 'olas', $slmId);
       $ticket = $this->createTicket(0, 0, [
           'slas_id_ttr' => $slaId,
           'olas_id_ttr' => $olaId,
       ]);
       $rule = $this->createRule('agreements', \RuleTicket::ONADD, true, 1, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [
           ['assign', 'slas_id_ttr', (string) $slaId],
           ['assign', 'olas_id_ttr', (string) $olaId],
       ]);

       $inspected = $this->findRule(
           (new RuleTicketInspector())->inspect(
               $ticket,
               \RuleTicket::ONADD,
               new InspectionOptions(1000, true)
           )->rules,
           $rule->getID()
       );

       self::assertSame(ActionEvaluation::REFLECTED, $this->findAction($inspected->actions, 'slas_id_ttr')->evaluation);
       self::assertSame(ActionEvaluation::REFLECTED, $this->findAction($inspected->actions, 'olas_id_ttr')->evaluation);
       self::assertContains(
           'Action reflection describes only the current Ticket snapshot, not historical causality.',
           $this->findInspectionLimitations($ticket, \RuleTicket::ONADD)
       );
   }

   public function testDynamicUnsupportedUpdateAndSequentialResultsRemainIndependent(): void {
       $ticket = $this->createTicket();
       $update = $this->createRule('update-actions', \RuleTicket::ONUPDATE, true, 1, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [
           ['assign', 'urgency', '3'],
           ['compute', 'priority', '1'],
           ['regex_result', '_affect_itilcategory_by_code', '#0'],
           ['append', 'task_template', '1'],
           ['assign', 'assign_project', '1'],
       ]);
       $first = $this->createRule('sequence-first', \RuleTicket::ONADD, true, 2, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [['assign', 'urgency', '5']]);
       $second = $this->createRule('sequence-second', \RuleTicket::ONADD, true, 3, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [['assign', 'urgency', '3']]);

       $updateResult = (new RuleTicketInspector())->inspect(
           $ticket,
           \RuleTicket::ONUPDATE,
           new InspectionOptions(1000, true)
       );
       $inspectedUpdate = $this->findRule($updateResult->rules, $update->getID());
       self::assertSame(Evaluation::INDETERMINATE, $inspectedUpdate->evaluation);
       self::assertSame(ActionEvaluation::REFLECTED, $this->findAction($inspectedUpdate->actions, 'urgency')->evaluation);
       self::assertSame(ActionSupport::INDETERMINATE_BY_DESIGN, $this->findAction($inspectedUpdate->actions, 'priority')->support);
       self::assertSame(ActionSupport::INDETERMINATE_BY_DESIGN, $this->findAction($inspectedUpdate->actions, '_affect_itilcategory_by_code')->support);
       self::assertSame(ActionSupport::INDETERMINATE_BY_DESIGN, $this->findAction($inspectedUpdate->actions, 'task_template')->support);
       self::assertSame(ActionSupport::UNSUPPORTED, $this->findAction($inspectedUpdate->actions, 'assign_project')->support);

       $addResult = (new RuleTicketInspector())->inspect(
           $ticket,
           \RuleTicket::ONADD,
           new InspectionOptions(1000, true)
       );
       self::assertSame(
           ActionEvaluation::NOT_REFLECTED,
           $this->findAction($this->findRule($addResult->rules, $first->getID())->actions, 'urgency')->evaluation
       );
       self::assertSame(
           ActionEvaluation::REFLECTED,
           $this->findAction($this->findRule($addResult->rules, $second->getID())->actions, 'urgency')->evaluation
       );
       self::assertStringContainsString('not historical', implode(' ', $addResult->limitations));
   }

    /** @param array<string, mixed> $overrides */
   private function createTicket(int $entityId = 0, int $categoryId = 0, array $overrides = []): \Ticket {
       $ticket = new \Ticket();
       $input = [
           'name' => $this->prefix,
           'content' => 'Phase 3 read-only inspection fixture',
           'entities_id' => $entityId,
           'itilcategories_id' => $categoryId,
           'status' => 1,
           'urgency' => 3,
           'impact' => 3,
           'priority' => 3,
           'requesttypes_id' => 1,
           '_users_id_requester' => (int) \Session::getLoginUserID(),
           '_skip_rules' => true,
       ];
       if (array_key_exists('_actors', $overrides)) {
          unset($input['_users_id_requester']);
       }
       $ticketId = (int) $ticket->add(array_replace($input, $overrides));
       if ($ticketId > 0) {
          $this->track('tickets', $ticketId);
       }
       self::assertGreaterThan(0, $ticketId);

       $loaded = new \Ticket();
       self::assertTrue($loaded->getFromDB($ticketId));
       return $loaded;
   }

    /**
     * @param list<array{string, int, string}> $criteria
     * @param list<array{string, string, mixed}> $actions
     */
   private function createRule(
        string $suffix,
        int $condition,
        bool $active,
        int $ranking,
        array $criteria = [],
        array $actions = [],
        int $entityId = 0,
        bool $recursive = true,
        string $match = \Rule::AND_MATCHING
    ): \RuleTicket {
       $rule = new \RuleTicket();
       $ruleId = (int) $rule->add([
           'name' => $this->prefix . '-' . $suffix,
           'entities_id' => $entityId,
           'sub_type' => \RuleTicket::class,
           'condition' => $condition,
           'is_active' => (int) $active,
           'is_recursive' => (int) $recursive,
           'match' => $match,
           'ranking' => $ranking,
       ]);
      if ($ruleId > 0) {
          $this->track('rules', $ruleId);
      }
       self::assertGreaterThan(0, $ruleId);

      foreach ($criteria as [$key, $operator, $pattern]) {
          $criterion = new \RuleCriteria();
          $criterionId = (int) $criterion->add([
              'rules_id' => $ruleId,
              'criteria' => $key,
              'condition' => $operator,
              'pattern' => $pattern,
          ]);
         if ($criterionId > 0) {
            $this->track('criteria', $criterionId);
         }
          self::assertGreaterThan(0, $criterionId);
      }

      foreach ($actions as [$type, $field, $value]) {
          $action = new \RuleAction();
          $actionId = (int) $action->add([
              'rules_id' => $ruleId,
              'action_type' => $type,
              'field' => $field,
              'value' => $value,
          ]);
         if ($actionId > 0) {
            $this->track('actions', $actionId);
         }
          self::assertGreaterThan(0, $actionId);
      }

       $loaded = new \RuleTicket();
       self::assertTrue($loaded->getRuleWithCriteriasAndActions($ruleId, true, true));
       return $loaded;
   }

   private function createCategory(string $code): int {
       $category = new \ITILCategory();
       $categoryId = (int) $category->add([
           'name' => $this->prefix . '-category',
           'entities_id' => 0,
           'is_recursive' => 1,
           'code' => $code,
       ]);
      if ($categoryId > 0) {
          $this->track('categories', $categoryId);
      }
       self::assertGreaterThan(0, $categoryId);
       return $categoryId;
   }

   private function createEntity(string $name, int $parentId): int {
       $entity = new \Entity();
       $entityId = (int) $entity->add(['name' => $name, 'entities_id' => $parentId]);
      if ($entityId > 0) {
          $this->track('entities', $entityId);
      }
       self::assertGreaterThan(0, $entityId);
       return $entityId;
   }

   private function createGroup(): int {
       $group = new \Group();
       $groupId = (int) $group->add([
           'name' => $this->prefix . '-group',
           'entities_id' => 0,
           'is_requester' => 1,
           'is_assign' => 1,
           'is_watcher' => 1,
       ]);
      if ($groupId > 0) {
          $this->track('groups', $groupId);
      }
       self::assertGreaterThan(0, $groupId);
       return $groupId;
   }

   private function createSupplier(): int {
       $supplier = new \Supplier();
       $supplierId = (int) $supplier->add([
           'name' => $this->prefix . '-supplier',
           'entities_id' => 0,
       ]);
      if ($supplierId > 0) {
          $this->track('suppliers', $supplierId);
      }
       self::assertGreaterThan(0, $supplierId);
       return $supplierId;
   }

   private function createSlm(): int {
       $slm = new \SLM();
       $slmId = (int) $slm->add([
           'name' => $this->prefix . '-slm',
           'calendars_id' => 0,
       ]);
      if ($slmId > 0) {
          $this->track('slms', $slmId);
      }
       self::assertGreaterThan(0, $slmId);
       return $slmId;
   }

    /** @param class-string<\SLA|\OLA> $class */
   private function createAgreement(string $class, string $type, int $slmId): int {
       $agreement = new $class();
       $agreementId = (int) $agreement->add([
           'name' => $this->prefix . '-' . $type,
           'slms_id' => $slmId,
           'type' => \SLM::TTR,
           'number_time' => 4,
           'definition_time' => 'hour',
       ]);
      if ($agreementId > 0) {
          $this->track($type, $agreementId);
      }
       self::assertGreaterThan(0, $agreementId);
       return $agreementId;
   }

    /**
     * @param list<\GlpiPlugin\Clarus\Inspector\RuleInspection> $rules
     */
   private function findRule(array $rules, int $ruleId): \GlpiPlugin\Clarus\Inspector\RuleInspection {
      foreach ($rules as $rule) {
         if ($rule->id === $ruleId) {
            return $rule;
         }
      }

       self::fail('Expected rule was not present in the inspection result.');
   }

    /** @param list<ActionInspection> $actions */
   private function findAction(array $actions, string $field): ActionInspection {
      foreach ($actions as $action) {
         if ($action->field === $field) {
             return $action;
         }
      }

       self::fail('Expected action was not present in the inspection result.');
   }

    /** @return list<string> */
   private function findInspectionLimitations(\Ticket $ticket, int $condition): array {
       return (new RuleTicketInspector())->inspect(
           $ticket,
           $condition,
           new InspectionOptions(1000, true)
       )->limitations;
   }

    /** @return array<string, mixed> */
   private function snapshot(int $ticketId, int $ruleId): array {
       $ticket = new \Ticket();
       self::assertTrue($ticket->getFromDB($ticketId));
       $rule = new \RuleTicket();
       self::assertTrue($rule->getRuleWithCriteriasAndActions($ruleId, true, true));

       return [
           'ticket' => $ticket->fields,
           'actors' => [
               'requester' => $ticket->getActorsForType(\CommonITILActor::REQUESTER),
               'assign' => $ticket->getActorsForType(\CommonITILActor::ASSIGN),
               'observer' => $ticket->getActorsForType(\CommonITILActor::OBSERVER),
           ],
           'rule' => $rule->fields,
           'criteria' => array_map(
               static fn (\RuleCriteria $criterion): array => $criterion->fields,
               $rule->criterias
           ),
           'actions' => array_map(
               static fn (\RuleAction $action): array => $action->fields,
               $rule->actions
           ),
       ];
   }

   private function track(string $type, int $id): void {
       $this->createdIds[$type][$id] = true;
       self::$pendingIds[$type][$id] = true;
   }

    /**
     * @param array<string, array<int, true>> $ids
     * @return list<string>
     */
   private static function cleanup(array $ids): array {
       $errors = [];
      foreach ([
           'tickets' => \Ticket::class,
           'actions' => \RuleAction::class,
            'criteria' => \RuleCriteria::class,
           'rules' => \RuleTicket::class,
            'slas' => \SLA::class,
            'olas' => \OLA::class,
            'slms' => \SLM::class,
            'suppliers' => \Supplier::class,
            'groups' => \Group::class,
            'categories' => \ITILCategory::class,
            'entities' => \Entity::class,
        ] as $type => $class) {
          $objectIds = array_keys($ids[$type]);
         if ($type === 'entities') {
             $objectIds = array_reverse($objectIds);
         }
         foreach ($objectIds as $id) {
            try {
               $item = new $class();
               if (!$item->getFromDB($id)) {
                    unset(self::$pendingIds[$type][$id]);
                    continue;
               }
               if (!$item->delete(['id' => $id], true)) {
                     $errors[] = sprintf('%s#%d delete returned false', $type, $id);
                     continue;
               }
                  unset(self::$pendingIds[$type][$id]);
            } catch (\Throwable $throwable) {
                $errors[] = sprintf('%s#%d: %s', $type, $id, $throwable->getMessage());
            }
         }
      }

       return $errors;
   }

   /** @return array<string, array<int, true>> */
   private static function emptyIdMap(): array {
       return [
           'tickets' => [],
           'actions' => [],
           'criteria' => [],
           'rules' => [],
            'slas' => [],
            'olas' => [],
            'slms' => [],
            'suppliers' => [],
            'groups' => [],
            'categories' => [],
           'entities' => [],
       ];
   }
}
