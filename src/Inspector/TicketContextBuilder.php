<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class TicketContextBuilder
{
    /** @var list<string> */
   private const ACTION_TARGET_FIELDS = [
       'validation_percent',
       'time_to_resolve',
       'time_to_own',
       'internal_time_to_resolve',
       'internal_time_to_own',
   ];

   private const RETROSPECTIVE_REASON =
        'Value cannot be reconstructed defensibly from the persisted Ticket state.';

   public function build(\Ticket $ticket): TicketContext {
      if ($ticket->isNewItem()) {
          throw new \InvalidArgumentException('Inspector requires a persisted Ticket.');
      }

       $rule = new \RuleTicket();
       $values = [];
      foreach (array_keys($rule->getCriterias()) as $key) {
          $values[$key] = ContextValue::indeterminate(self::RETROSPECTIVE_REASON);
      }
      foreach (self::ACTION_TARGET_FIELDS as $key) {
          $values[$key] = ContextValue::indeterminate(self::RETROSPECTIVE_REASON);
      }

      foreach ($values as $key => $unused) {
         if (array_key_exists($key, $ticket->fields)) {
             $values[$key] = ContextValue::available(
                 $ticket->fields[$key],
                 'ticket',
                 $this->isPresentationSafe($key)
             );
         }
      }

       $this->addCategoryCode($ticket, $values);
       $this->addActors($ticket, $values);
       $this->addRequesterData($values);

       return new TicketContext($values);
   }

    /** @param array<string, ContextValue> $values */
   private function addCategoryCode(\Ticket $ticket, array &$values): void {
       $categoryId = NativeField::integer($ticket->fields['itilcategories_id'] ?? 0);
      if ($categoryId === 0) {
          $values['itilcategories_id_code'] = ContextValue::available('', 'derived:category', false);
          return;
      }

       $category = \ITILCategory::getById($categoryId);
      if ($category instanceof \ITILCategory && array_key_exists('code', $category->fields)) {
          $values['itilcategories_id_code'] = ContextValue::available(
              $category->fields['code'],
              'derived:category',
              false
          );
      }
   }

    /** @param array<string, ContextValue> $values */
   private function addActors(\Ticket $ticket, array &$values): void {
       $actorFields = [
           \CommonITILActor::REQUESTER => ['User' => '_users_id_requester', 'Group' => '_groups_id_requester'],
           \CommonITILActor::ASSIGN => [
               'User' => '_users_id_assign',
               'Group' => '_groups_id_assign',
               'Supplier' => '_suppliers_id_assign',
           ],
           \CommonITILActor::OBSERVER => ['User' => '_users_id_observer', 'Group' => '_groups_id_observer'],
       ];

       foreach ($actorFields as $actorType => $mappings) {
           $actors = $ticket->getActorsForType($actorType);
          foreach ($mappings as $itemType => $contextKey) {
              $ids = [];
             foreach ($actors as $actor) {
                if (($actor['itemtype'] ?? null) === $itemType) {
                    $id = NativeField::integer($actor['items_id'] ?? 0);
                    $ids[$id] = $id;
                }
             }
              $values[$contextKey] = ContextValue::available(array_values($ids), 'derived:actors', true);
          }
       }
   }

    /** @param array<string, ContextValue> $values */
   private function addRequesterData(array &$values): void {
       $requesters = $values['_users_id_requester']->value ?? [];
      if (!is_array($requesters) || $requesters === []) {
          $values['_groups_id_of_requester'] = ContextValue::available([], 'derived:requester', true);
          return;
      }

       $groups = [];
      foreach ($requesters as $requesterId) {
         foreach (\Group_User::getUserGroups(NativeField::integer($requesterId)) as $group) {
             $groupId = NativeField::integer($group['id'] ?? 0);
            if ($groupId > 0) {
               $groups[$groupId] = $groupId;
            }
         }
      }
       $values['_groups_id_of_requester'] = ContextValue::available(
           array_values($groups),
           'derived:requester-groups',
           true
       );

       $user = new \User();
       $firstRequesterId = NativeField::integer(reset($requesters));
      if ($firstRequesterId > 0 && $user->getFromDB($firstRequesterId)) {
         foreach ([
              '_locations_id_of_requester' => 'locations_id',
              'profiles_id' => 'profiles_id',
          ] as $contextKey => $userField) {
            if (array_key_exists($userField, $user->fields)) {
                 $values[$contextKey] = ContextValue::available(
                     $user->fields[$userField],
                     'derived:requester',
                     true
                 );
            }
         }
      }
   }

   private function isPresentationSafe(string $key): bool {
       return !in_array($key, [
           'name',
           'content',
           '_from',
           '_subject',
           '_reply-to',
           '_in-reply-to',
           '_to',
       ], true);
   }
}
