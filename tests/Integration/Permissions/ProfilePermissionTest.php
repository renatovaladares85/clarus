<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Integration\Permissions;

use GlpiPlugin\Clarus\Authorization;
use GlpiPlugin\Clarus\Profile as ClarusProfile;
use PHPUnit\Framework\TestCase;

/** @group glpi-integration */
final class ProfilePermissionTest extends TestCase
{
   private int $bootstrapProfileId;

   private int $bootstrapRight;

    /** @var array<string, list<int>> */
   private array $created = [
        'tickets' => [],
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
   }

   protected function tearDown(): void {
       $errors = [];

      try {
          $this->loginAsProfile($this->bootstrapProfileId);
          \ProfileRight::updateProfileRights($this->bootstrapProfileId, [
              ClarusProfile::RIGHT_INSPECT => $this->bootstrapRight,
          ]);
          $this->loginAsProfile($this->bootstrapProfileId);
      } catch (\Throwable $throwable) {
          $errors[] = 'profile restoration: ' . $throwable->getMessage();
      }

      foreach ([
           'tickets' => \Ticket::class,
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

   public function testInstallRegistersOneDeniedRightForTheBootstrapSuperAdminProfile(): void {
       self::assertSame(0, $this->bootstrapRight);
       self::assertFalse(\Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertTrue(plugin_clarus_install());
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId));
       self::assertSame(1, $this->rightRowCount($this->bootstrapProfileId));
   }

   public function testGrantAndRevokeControlTicketInspection(): void {
       $profileId = $this->createRestrictedProfile(false);
       $ticket = $this->createTicket(0);

       $this->loginAsProfile($profileId);
       self::assertTrue($ticket->canViewItem());
       self::assertFalse(Authorization::canInspectTicket($ticket));

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($profileId);
       self::assertTrue(\Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertTrue($ticket->canViewItem());
       self::assertTrue(Authorization::canInspectTicket($ticket));

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => 0]);
       $this->loginAsProfile($profileId);
       self::assertFalse(\Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse(Authorization::canInspectTicket($ticket));
   }

   public function testTicketEntityAccessRemainsNativeAndCanBeRecursive(): void {
       $profileId = $this->createRestrictedProfile(false);
       $entityId = $this->createEntity();
       $ticket = $this->createTicket($entityId);

       \ProfileRight::updateProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT => READ]);
       $this->loginAsProfile($profileId);
       self::assertTrue(\Session::haveRight(ClarusProfile::RIGHT_INSPECT, READ));
       self::assertFalse($ticket->canViewItem());
       self::assertFalse(Authorization::canInspectTicket($ticket));

       $profileUser = new \Profile_User();
       self::assertTrue($profileUser->getFromDB($this->created['profile_users'][0]));
       self::assertTrue($profileUser->update([
           'id' => $profileUser->getID(),
           'is_recursive' => 1,
       ]));

       $this->loginAsProfile($profileId);
       self::assertTrue($ticket->canViewItem());
       self::assertTrue(Authorization::canInspectTicket($ticket));
   }

   public function testUninstallAndReinstallRemoveAndRecreateOnlyTheClarusRight(): void {
       self::assertTrue(plugin_clarus_uninstall());
       self::assertSame(0, $this->rightRowCount($this->bootstrapProfileId));

       self::assertTrue(plugin_clarus_install());
       self::assertSame(0, $this->rightValue($this->bootstrapProfileId));
       self::assertSame(1, $this->rightRowCount($this->bootstrapProfileId));
   }

   private function createRestrictedProfile(bool $recursive): int {
       $profile = new \Profile();
       $profileId = (int) $profile->add([
           'name' => 'clarus-phase5-' . str_replace('.', '', uniqid('', true)),
           'interface' => 'central',
       ]);
       self::assertGreaterThan(0, $profileId);
       $this->created['profiles'][] = $profileId;

       \ProfileRight::updateProfileRights($profileId, [
           'ticket' => \Ticket::READMY,
           ClarusProfile::RIGHT_INSPECT => 0,
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

   private function loginAsProfile(int $profileId): void {
       \Session::destroy();
       \Session::start();
       $auth = new \Auth();
       self::assertTrue($auth->login('glpi', 'glpi', true));
       \Session::changeProfile($profileId);
       self::assertSame($profileId, (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0));
   }

   private function rightValue(int $profileId): int {
       $rights = \ProfileRight::getProfileRights($profileId, [ClarusProfile::RIGHT_INSPECT]);

       return (int) ($rights[ClarusProfile::RIGHT_INSPECT] ?? -1);
   }

   private function rightRowCount(int $profileId): int {
       global $DB;

      foreach ($DB->request([
           'COUNT' => 'count',
           'FROM' => \ProfileRight::getTable(),
           'WHERE' => [
               'profiles_id' => $profileId,
               'name' => ClarusProfile::RIGHT_INSPECT,
           ],
       ]) as $row) {
          return (int) $row['count'];
      }

       return 0;
   }
}
