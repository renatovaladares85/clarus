<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/**
 * Pure counterpart to RuleTicketCollection::prepareInputDataForProcess().
 *
 * It deliberately does not invoke GLPI plugin hooks. A hook-provided value is
 * not reconstructable from a persisted Ticket unless it is already durable
 * evidence in the context, so replay must keep it unknown rather than run an
 * arbitrary hook or fabricate a value.
 */
final class RuleTicketInputPreparer
{
   /** @var array<string, string> */
   private const HEADER_KEYS = [
       'x-priority' => '_x-priority',
       'from' => '_from',
       'subject' => '_subject',
       'reply-to' => '_reply-to',
       'in-reply-to' => '_in-reply-to',
       'to' => '_to',
   ];

   public function prepare(TicketContext $context): TicketContext {
       $head = $context->get('_head');
      if ($head->state === ContextState::AVAILABLE && is_array($head->value)) {
         foreach (self::HEADER_KEYS as $header => $contextKey) {
            if (array_key_exists($header, $head->value)) {
               $context = $context->with(
                   $contextKey,
                   ContextValue::available($head->value[$header], 'derived:mail-header')
               );
            }
         }
      }

       $requesters = $context->get('_users_id_requester');
      if ($requesters->state === ContextState::AVAILABLE && !str_starts_with($requesters->source, 'history:')) {
          $ids = is_array($requesters->value) ? $requesters->value : [$requesters->value];
          $groups = [];
         foreach ($ids as $requesterId) {
            $requesterId = NativeField::integer($requesterId);
            if ($requesterId < 1) {
                continue;
            }
            foreach (\Group_User::getUserGroups($requesterId) as $group) {
                $groupId = NativeField::integer($group['id'] ?? 0);
               if ($groupId > 0) {
                   $groups[$groupId] = $groupId;
               }
            }
         }
          $context = $context->with(
              '_groups_id_of_requester',
              ContextValue::available(array_values($groups), 'derived:requester-groups', true)
          );
      } else if ($requesters->state === ContextState::AVAILABLE
          && $context->get('_groups_id_of_requester')->state !== ContextState::AVAILABLE) {
          // A past requester ID does not prove its group membership at that
          // time. Reading today's membership would silently change semantics.
          $context = $context->with(
              '_groups_id_of_requester',
              ContextValue::indeterminate('historical_requester_group_membership_not_persisted')
          );
      }

       $category = $context->get('itilcategories_id');
       $categoryCode = $context->get('itilcategories_id_code');
      if ($category->state === ContextState::AVAILABLE
          && !str_starts_with($category->source, 'history:')
          && NativeField::integer($category->value) > 0) {
          $item = \ITILCategory::getById(NativeField::integer($category->value));
         if ($item instanceof \ITILCategory && array_key_exists('code', $item->fields)) {
             $context = $context->with(
                 'itilcategories_id_code',
                 ContextValue::available($item->fields['code'], 'derived:category')
             );
         } else {
             $context = $context->with(
                 'itilcategories_id_code',
                 ContextValue::indeterminate('category_code_not_reconstructible')
             );
         }
      } else if ($category->state === ContextState::AVAILABLE
          && $categoryCode->state !== ContextState::AVAILABLE) {
          $context = $context->with(
              'itilcategories_id_code',
              ContextValue::indeterminate('historical_category_code_not_persisted')
          );
      }

       return $context;
   }
}
