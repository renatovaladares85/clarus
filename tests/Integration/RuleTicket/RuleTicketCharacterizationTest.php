<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Integration\RuleTicket;

use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION TESTS ONLY: never a Clarus runtime dependency.
 *
 * @group glpi-integration
 */
final class RuleTicketCharacterizationTest extends TestCase
{
    /** @var array<string, array<int, true>> */
   private static array $pendingIds = [
        'actions' => [],
        'criteria' => [],
        'rules' => [],
        'entities' => [],
    ];

    /** @var array<string, array<int, true>> */
   private array $createdIds = [
        'actions' => [],
        'criteria' => [],
        'rules' => [],
        'entities' => [],
    ];

   private string $prefix;

   protected function setUp(): void {
       parent::setUp();
       $this->prefix = 'clarus-phase2-' . str_replace('.', '', uniqid('', true));
   }

   protected function tearDown(): void {
       $errors = self::cleanup($this->createdIds);
       $this->createdIds = self::emptyIdMap();
       parent::tearDown();

      if ($errors !== []) {
          self::fail('RuleTicket characterization cleanup failed: ' . implode('; ', $errors));
      }
   }

   public static function tearDownAfterClass(): void {
       $errors = self::cleanup(self::$pendingIds);

      if ($errors !== []) {
          throw new \RuntimeException(
              'RuleTicket characterization fallback cleanup failed: ' . implode('; ', $errors)
          );
      }
   }

   public function testSelectionFiltersActiveConditionAndOrdersByEntityThenRanking(): void {
       $childId = $this->createEntity($this->prefix . '-child', 0);

       $parentRecursive = $this->createRule('parent-recursive', 0, \RuleTicket::ONADD, true, 90, true);
       $parentFlat = $this->createRule('parent-flat', 0, \RuleTicket::ONADD, true, 80, false);
       $inactive = $this->createRule('inactive', $childId, \RuleTicket::ONADD, false, 1);
       $update = $this->createRule('update', $childId, \RuleTicket::ONUPDATE, true, 2);
       $childRule = $this->createRule('child', $childId, \RuleTicket::ONADD, true, 10);

       $addIds = $this->collectionIds($childId, \RuleTicket::ONADD);
       self::assertContains($parentRecursive->getID(), $addIds);
       self::assertContains($childRule->getID(), $addIds);
       self::assertNotContains($parentFlat->getID(), $addIds);
       self::assertNotContains($inactive->getID(), $addIds);
       self::assertNotContains($update->getID(), $addIds);
       self::assertLessThan(
           array_search($childRule->getID(), $addIds, true),
           array_search($parentRecursive->getID(), $addIds, true)
       );

       self::assertContains($update->getID(), $this->collectionIds($childId, \RuleTicket::ONUPDATE));
   }

   public function testNativeAndOrAndGenericOperators(): void {
       $and = $this->createRule('and', 0, \RuleTicket::ONADD, true, 1, true, \Rule::AND_MATCHING, [
           ['name', \Rule::PATTERN_IS, 'alpha'],
           ['content', \Rule::PATTERN_CONTAIN, 'bravo'],
       ]);
       self::assertTrue($and->checkCriterias(['name' => 'ALPHA', 'content' => 'bravo text']));
       self::assertFalse($and->checkCriterias(['name' => 'alpha', 'content' => 'missing']));

       $or = $this->createRule('or', 0, \RuleTicket::ONADD, true, 2, true, \Rule::OR_MATCHING, [
           ['name', \Rule::PATTERN_IS, 'alpha'],
           ['content', \Rule::PATTERN_IS, 'bravo'],
       ]);
       self::assertTrue($or->checkCriterias(['name' => 'missing', 'content' => 'bravo']));
       self::assertFalse($or->checkCriterias(['name' => 'missing', 'content' => 'missing']));

      foreach (self::operatorCases() as [$operator, $pattern, $value]) {
          $rule = $this->createRule('operator-' . $operator, 0, \RuleTicket::ONADD, true, 20 + $operator, true, \Rule::AND_MATCHING, [
              ['name', $operator, $pattern],
          ]);
          self::assertTrue($rule->checkCriterias(['name' => $value]));
      }
   }

   public function testTreeAndArrayCriteriaUseNativeSemantics(): void {
       $parentId = $this->createEntity($this->prefix . '-tree-parent', 0);
       $childId = $this->createEntity($this->prefix . '-tree-child', $parentId);

       $under = $this->createRule('under', 0, \RuleTicket::ONADD, true, 1, true, \Rule::AND_MATCHING, [
           ['entities_id', \Rule::PATTERN_UNDER, (string) $parentId],
       ]);
       self::assertArrayHasKey($childId, getSonsOf(\Entity::getTable(), $parentId));
       self::assertTrue($under->checkCriterias(['entities_id' => $childId]));

       $positive = $this->createRule('array-positive', 0, \RuleTicket::ONADD, true, 2, true, \Rule::AND_MATCHING, [
           ['_users_id_requester', \Rule::PATTERN_IS, '17'],
       ]);
       $negative = $this->createRule('array-negative', 0, \RuleTicket::ONADD, true, 3, true, \Rule::AND_MATCHING, [
           ['_users_id_requester', \Rule::PATTERN_IS_NOT, '17'],
       ]);
       self::assertTrue($positive->checkCriterias(['_users_id_requester' => [7, 17]]));
       self::assertFalse($negative->checkCriterias(['_users_id_requester' => [7, 17]]));
   }

   public function testProcessAllRulesPassesOutputToTheNextRule(): void {
       $first = $this->createRule('sequential-first', 0, \RuleTicket::ONADD, true, 1, true, \Rule::AND_MATCHING, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [['assign', 'urgency', '4']]);
       $second = $this->createRule('sequential-second', 0, \RuleTicket::ONADD, true, 2, true, \Rule::AND_MATCHING, [
           ['urgency', \Rule::PATTERN_IS, '4'],
       ], [['assign', 'impact', '5']]);

       $collection = new \RuleTicketCollection(0);
       $collection->RuleList = new \SingletonRuleList();
       $collection->RuleList->list = [$first, $second];
       $collection->RuleList->load = 15;
       $input = ['entities_id' => 0, 'name' => $this->prefix, 'urgency' => '1', 'impact' => '1'];
       $output = $collection->processAllRules($input, $input, ['recursive' => true, 'entities_id' => 0], [
           'condition' => \RuleTicket::ONADD,
       ]);

       self::assertSame('4', (string) $output['urgency']);
       self::assertSame('5', (string) $output['impact']);
   }

   public function testCheckCriteriasDoesNotExecuteConfiguredActions(): void {
       $rule = $this->createRule('read-only', 0, \RuleTicket::ONADD, true, 1, true, \Rule::AND_MATCHING, [
           ['name', \Rule::PATTERN_IS, $this->prefix],
       ], [['assign', 'urgency', '5']]);
       $input = ['name' => $this->prefix, 'urgency' => '1'];

       self::assertTrue($rule->checkCriterias($input));
       self::assertSame('1', $input['urgency']);
       $reloaded = new \RuleTicket();
       self::assertTrue($reloaded->getRuleWithCriteriasAndActions($rule->getID(), true, true));
       self::assertSame('5', (string) $reloaded->actions[0]->fields['value']);
   }

    /** @return array<int, array{int, string, string}> */
   private static function operatorCases(): array {
       return [
           [\Rule::PATTERN_IS, 'alpha', 'ALPHA'],
           [\Rule::PATTERN_IS_NOT, 'alpha', 'bravo'],
           [\Rule::PATTERN_CONTAIN, 'pha', 'alpha'],
           [\Rule::PATTERN_NOT_CONTAIN, 'zulu', 'alpha'],
           [\Rule::PATTERN_BEGIN, 'alp', 'alpha'],
           [\Rule::PATTERN_END, 'pha', 'alpha'],
           [\Rule::REGEX_MATCH, '/^a(.*)a$/', 'alpha'],
           [\Rule::REGEX_NOT_MATCH, '/^z/', 'alpha'],
           [\Rule::PATTERN_EXISTS, 'ignored', 'alpha'],
           [\Rule::PATTERN_DOES_NOT_EXISTS, 'ignored', ''],
       ];
   }

    /**
     * @param array<int, array{string, int, string}> $criteria
     * @param array<int, array{string, string, string}> $actions
     */
   private function createRule(string $suffix, int $entityId, int $condition, bool $active, int $ranking, bool $recursive = true, string $match = \Rule::AND_MATCHING, array $criteria = [], array $actions = []): \RuleTicket {
       $rule = new \RuleTicket();
       $ruleId = (int) $rule->add([
           'name' => $this->prefix . '-' . $suffix, 'entities_id' => $entityId, 'sub_type' => \RuleTicket::class,
           'condition' => $condition, 'is_active' => (int) $active, 'is_recursive' => (int) $recursive,
           'match' => $match, 'ranking' => $ranking,
       ]);
      if ($ruleId > 0) {
          $this->track('rules', $ruleId);
      }
       self::assertGreaterThan(0, $ruleId);
      foreach ($criteria as [$criterion, $operator, $pattern]) {
          $row = new \RuleCriteria();
          $criterionId = (int) $row->add([
              'rules_id' => $ruleId,
              'criteria' => $criterion,
              'condition' => $operator,
              'pattern' => $pattern,
          ]);
         if ($criterionId > 0) {
            $this->track('criteria', $criterionId);
         }
          self::assertGreaterThan(0, $criterionId);
      }
      foreach ($actions as [$type, $field, $value]) {
          $row = new \RuleAction();
          $actionId = (int) $row->add([
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

   private function createEntity(string $name, int $parentId): int {
       $entity = new \Entity();
       $entityId = (int) $entity->add(['name' => $name, 'entities_id' => $parentId]);
      if ($entityId > 0) {
          $this->track('entities', $entityId);
      }
       self::assertGreaterThan(0, $entityId);
       return $entityId;
   }

   private function track(string $type, int $id): void {
       $this->createdIds[$type][$id] = true;
       self::$pendingIds[$type][$id] = true;
   }

    /** @param array<string, array<int, true>> $ids
     *  @return list<string>
     */
   private static function cleanup(array $ids): array {
       $errors = [];
      foreach ([
           'actions' => \RuleAction::class,
           'criteria' => \RuleCriteria::class,
           'rules' => \RuleTicket::class,
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
                   throw new \RuntimeException(sprintf('%s #%d was not purged.', $class, $id));
               }
               if ($item->getFromDB($id)) {
                   throw new \RuntimeException(sprintf('%s #%d still exists after purge.', $class, $id));
               }
                unset(self::$pendingIds[$type][$id]);
            } catch (\Throwable $exception) {
                $errors[] = sprintf('%s #%d: %s', $class, $id, $exception->getMessage());
            }
         }
      }
       return $errors;
   }

    /** @return array<string, array<int, true>> */
   private static function emptyIdMap(): array {
       return ['actions' => [], 'criteria' => [], 'rules' => [], 'entities' => []];
   }

    /** @return list<int> */
   private function collectionIds(int $entityId, int $condition): array {
       $collection = new \RuleTicketCollection($entityId);
       $collection->getCollectionDatas(1, 1, $condition);
       return array_map(static fn (\RuleTicket $rule): int => (int) $rule->getID(), $collection->RuleList->list);
   }
}
