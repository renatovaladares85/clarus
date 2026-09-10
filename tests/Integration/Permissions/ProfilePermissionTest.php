<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Integration\Permissions;

use GlpiPlugin\Clarus\Authorization;
use GlpiPlugin\Clarus\Profile as ClarusProfile;
use GlpiPlugin\Clarus\TicketTab;
use PHPUnit\Framework\TestCase;

/** @group glpi-integration */
final class ProfilePermissionTest extends TestCase
{
   private int $bootstrapProfileId;

   private int $bootstrapRight;

   private int $bootstrapSensitiveRight;

    /** @var array<string, list<int>> */
   private array $created = [
        'tickets' => [],
        'actions' => [],
        'criteria' => [],
        'rules' => [],
        'entities' => [],
        'profile_users' => [],
        'profiles' => [],
    ];

   protected function setUp(): void {
       parent::setUp();

       self::assertTrue(plugin_clarus_install());
       $this->bootstrapProfileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
       self::assertGreaterThan(0, $this->bootstrapProfileId);
       $this->bootstrapRight = $this->rightValue($this->bootstrapProfileId);
       $this->bootstrapSensitiveRight = $this->rightValue($this->bootstrapProfileId, ClarusProfile::RIGHT_SHOW_SENSITIVE);
   }

   protected function tearDown(): void {
       $errors = [];

      try {
          $this->loginAsProfile($this->bootstrapProfileId);
          \ProfileRight::updateProfileRights($this->bootstrapProfileId, [
              ClarusProfile::RIGHT_INSPECT => $this->bootstrapRight,
              ClarusProfile::RIGHT_SHOW_SENSITIVE => $this->bootstrapSensitiveRight,
          ]);
          $this->loginAsProfile($this->bootstrapProfileId);
      } catch (\Throwable $throwable) {
          $errors[] = 'profile restoration: ' . $throwable->getMessage();
      }

      foreach ([
           'tickets' => \Ticket::class,
           'actions' => \RuleAction::class,
           'criteria' => \RuleCriteria::class,
           'rules' => \RuleTicket::class,
           'profile_users' => \Profile_User::class,
           'profiles' => \Profile::class,
           'entities' => \Entity::class,
       ] as $type => $class) {
          $ids = $this->created[$type];
         if ($type === 'entities') {
             $ids = array_reverse($ids);
         }

         foreach ($ids as $id) {
            try {
                $item = new $class();
               if ($item->getFromDB($id) && !$item->delete(['id' => $id], true)) {
                  $errors[] = sprintf('%s#%d delete returned false', $type, $id);
               }
            } catch (\Throwable $throwable) {
                $errors[] = sprintf('%s#%d: %s', $type, $id, $throwable->getMessage());
            }
         }
      }

       parent::tearDown();
      if ($errors !== []) {
          self::fail('Permissions cleanup failed: ' . implode('; ', $errors));
      }
   }

   public function testInstallRegistersDeniedRightsForTheBootstrapSuperAdminProfile(): void {
       self::assertSame(0, $this->bootstrapRight);
       self::assertSame(0, $this->bootstrapSensitiveRight);
       self::assertFalse((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse(Authorization::canViewSensitiveInspectionValues());
       self::assertTrue(plugin_clarus_install());
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId));
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId, ClarusProfile::RIGHT_SHOW_SENSITIVE));
       self::assertSame(2, $this->rightRowCount($this->bootstrapProfileId));
   }

   public function testGrantAndRevokeControlTicketInspection(): void {
       $profileId = $this->createRestrictedProfile(false);
       $ticket = $this->createTicket(0);

       $this->loginAsProfile($profileId);
       self::assertTrue((bool) $ticket->canViewItem());
       self::assertFalse(Authorization::canInspectTicket($ticket));

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($profileId);
       self::assertTrue((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertTrue((bool) $ticket->canViewItem());
       self::assertTrue(Authorization::canInspectTicket($ticket));
       self::assertFalse(Authorization::canViewSensitiveInspectionValues());

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_SHOW_SENSITIVE => READ]);
       $this->loginAsProfile($profileId);
       self::assertTrue(Authorization::canViewSensitiveInspectionValues());

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => 0]);
       $this->loginAsProfile($profileId);
       self::assertFalse((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse(Authorization::canInspectTicket($ticket));
       self::assertTrue(Authorization::canViewSensitiveInspectionValues());
   }

   public function testTicketEntityAccessRemainsNativeAndCanBeRecursive(): void {
       $profileId = $this->createRestrictedProfile(false);
       $entityId = $this->createEntity();
       $ticket = $this->createTicket($entityId);

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($profileId);
       self::assertTrue((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse((bool) $ticket->canViewItem());
       self::assertFalse(Authorization::canInspectTicket($ticket));
       self::assertSame('', (new TicketTab())->getTabNameForItem($ticket));
       ob_start();
       self::assertFalse(TicketTab::displayTabContentForItem($ticket));
       self::assertSame('', ob_get_clean());

       $profileUser = new \Profile_User();
       self::assertTrue($profileUser->getFromDB($this->created['profile_users'][0]));
       self::assertTrue($profileUser->update([
           'id' => $profileUser->getID(),
           'is_recursive' => 1,
       ]));

       $this->loginAsProfile($profileId);
       self::assertTrue((bool) $ticket->canViewItem());
       self::assertTrue(Authorization::canInspectTicket($ticket));
   }

   public function testAuthorizedTicketTabRendersCurrentStateDiagnosticsReadOnly(): void {
       $profileId = $this->createRestrictedProfile(false);
       $ticket = $this->createTicket(0);
       $addRule = $this->createRule(
           'add',
           \RuleTicket::ONADD,
           (string) $ticket->fields['name'],
           [['assign', 'urgency', '3']]
       );
       $this->createRule(
           'update',
           \RuleTicket::ONUPDATE,
           (string) $ticket->fields['name'],
           [['assign', 'urgency', '3']]
       );
       $tab = new TicketTab();

       $this->loginAsProfile($profileId);
       self::assertSame('', $tab->getTabNameForItem($ticket));
       ob_start();
       self::assertFalse(TicketTab::displayTabContentForItem($ticket));
       self::assertSame('', ob_get_clean());

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($profileId);
       self::assertSame('Rule inspection', $tab->getTabNameForItem($ticket));
       $before = $this->ticketFields($ticket->getID());

       ob_start();
       self::assertTrue(TicketTab::displayTabContentForItem($ticket));
       $output = (string) ob_get_clean();

       self::assertStringContainsString('Current-state diagnostic of the last saved Ticket data only', $output);
       self::assertStringContainsString('Unsaved form changes are not included in this diagnostic.', $output);
       self::assertStringContainsString('Reflected in current state', $output);
       self::assertStringContainsString('Confirmed adherence', $output);
       self::assertStringNotContainsString('Update eligibility depends on the original change set', $output);
       self::assertStringContainsString('Not evaluated', $output);
       self::assertStringContainsString('No configured action was executed', $output);
       self::assertStringContainsString('data-clarus-inspection', $output);
       self::assertStringNotContainsString('PARTIAL_MATCH', $output);
       self::assertStringNotContainsString('Clarus Phase 5 authorization fixture', $output);
       self::assertSame($before, $this->ticketFields($ticket->getID()));

       $reloadedRule = new \RuleTicket();
       self::assertTrue($reloadedRule->getRuleWithCriteriasAndActions($addRule->getID(), true, true));
       self::assertSame('3', (string) $reloadedRule->actions[0]->fields['value']);
   }

   public function testConfigurationAccessUsesTheNativeRightIndependentlyOfInspection(): void {
       self::assertTrue((bool) \Session::haveRight('config', UPDATE));

       $configProfileId = $this->createRestrictedProfile(false, true);
       $this->loginAsProfile($configProfileId);
       self::assertTrue((bool) \Session::haveRight('config', UPDATE));
       self::assertFalse((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));

       $inspectionProfileId = $this->createRestrictedProfile(false);
       \ProfileRight::updateProfileRights($inspectionProfileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($inspectionProfileId);
       self::assertFalse((bool) \Session::haveRight('config', UPDATE));
       self::assertTrue((bool) \Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse(Authorization::canViewSensitiveInspectionValues());
   }

   public function testUninstallAndReinstallRemoveAndRecreateOnlyTheClarusRights(): void {
       self::assertTrue(plugin_clarus_uninstall());
       self::assertSame(0, $this->rightRowCount($this->bootstrapProfileId));

       self::assertTrue(plugin_clarus_install());
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId));
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId, ClarusProfile::RIGHT_SHOW_SENSITIVE));
       self::assertSame(2, $this->rightRowCount($this->bootstrapProfileId));
   }

   private function createRestrictedProfile(bool $recursive, bool $canConfigure = false): int {
       $profile = new \Profile();
       $profileId = (int) $profile->add([
           'name' => 'clarus-phase5-' . str_replace('.', '', uniqid('', true)),
           'interface' => 'central',
       ]);
       self::assertGreaterThan(0, $profileId);
       $this->created['profiles'][] = $profileId;

       \ProfileRight::updateProfileRights($profileId, [
           'ticket' => \Ticket::READMY,
           'config' => $canConfigure ? UPDATE : 0,
           ClarusProfile::RIGHT_INSPECT => 0,
           ClarusProfile::RIGHT_SHOW_SENSITIVE => 0,
       ]);

       $profileUser = new \Profile_User();
       $profileUserId = (int) $profileUser->add([
           'users_id' => \Session::getLoginUserID(),
           'profiles_id' => $profileId,
           'entities_id' => 0,
           'is_recursive' => (int) $recursive,
       ]);
       self::assertGreaterThan(0, $profileUserId);
       $this->created['profile_users'][] = $profileUserId;

       return $profileId;
   }

   private function createEntity(): int {
       $entity = new \Entity();
       $entityId = (int) $entity->add([
           'name' => 'clarus-phase5-' . str_replace('.', '', uniqid('', true)),
           'entities_id' => 0,
       ]);
       self::assertGreaterThan(0, $entityId);
       $this->created['entities'][] = $entityId;

       return $entityId;
   }

   private function createTicket(int $entityId): \Ticket {
       $ticket = new \Ticket();
       $ticketId = (int) $ticket->add([
           'name' => 'clarus-phase5-' . str_replace('.', '', uniqid('', true)),
           'content' => 'Clarus Phase 5 authorization fixture',
           'entities_id' => $entityId,
           'status' => \Ticket::INCOMING,
           'urgency' => 3,
           'impact' => 3,
           'priority' => 3,
           'requesttypes_id' => 1,
           '_users_id_requester' => \Session::getLoginUserID(),
           '_skip_rules' => true,
       ]);
       self::assertGreaterThan(0, $ticketId);
       $this->created['tickets'][] = $ticketId;

       $loaded = new \Ticket();
       self::assertTrue($loaded->getFromDB($ticketId));

       return $loaded;
   }

   /** @param list<array{string, string, string}> $actions */
   private function createRule(string $suffix, int $condition, string $name, array $actions): \RuleTicket {
       $rule = new \RuleTicket();
       $ruleId = (int) $rule->add([
           'name' => 'clarus-phase6-' . $suffix . '-' . str_replace('.', '', uniqid('', true)),
           'entities_id' => 0,
           'sub_type' => \RuleTicket::class,
           'condition' => $condition,
           'is_active' => 1,
           'is_recursive' => 1,
           'match' => \Rule::AND_MATCHING,
           'ranking' => 1,
       ]);
       self::assertGreaterThan(0, $ruleId);
       $this->created['rules'][] = $ruleId;

       $criterion = new \RuleCriteria();
       $criterionId = (int) $criterion->add([
           'rules_id' => $ruleId,
           'criteria' => 'name',
           'condition' => \Rule::PATTERN_IS,
           'pattern' => $name,
       ]);
       self::assertGreaterThan(0, $criterionId);
       $this->created['criteria'][] = $criterionId;

      foreach ($actions as [$actionType, $field, $value]) {
           $action = new \RuleAction();
           $actionId = (int) $action->add([
               'rules_id' => $ruleId,
               'action_type' => $actionType,
               'field' => $field,
               'value' => $value,
           ]);
           self::assertGreaterThan(0, $actionId);
           $this->created['actions'][] = $actionId;
      }

       $loaded = new \RuleTicket();
       self::assertTrue($loaded->getRuleWithCriteriasAndActions($ruleId, true, true));

       return $loaded;
   }

   /** @return array<string, mixed> */
   private function ticketFields(int $ticketId): array {
       $ticket = new \Ticket();
       self::assertTrue($ticket->getFromDB($ticketId));

       return $ticket->fields;
   }

   private function loginAsProfile(int $profileId): void {
       \Session::destroy();
       \Session::start();
       $auth = new \Auth();
       self::assertTrue($auth->login('glpi', 'glpi', true));
       \Session::changeProfile($profileId);
       self::assertSame($profileId, (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0));
   }

   private function rightValue(int $profileId, string $right = ClarusProfile::RIGHT_INSPECT): int {
       $rights = \ProfileRight::getProfileRights($profileId, [$right]);

       return (int) ($rights[$right] ?? -1);
   }

   private function rightRowCount(int $profileId): int {
       global $DB;

      foreach ($DB->request([
           'COUNT' => 'count',
           'FROM' => \ProfileRight::getTable(),
           'WHERE' => [
               'profiles_id' => $profileId,
               \ProfileRight::getTable() . '.name' => array_keys(ClarusProfile::getAllRights()),
           ],
       ]) as $row) {
          return (int) $row['count'];
      }

       return 0;
   }
}
