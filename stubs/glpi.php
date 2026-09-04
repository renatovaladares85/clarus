<?php
// SPDX-License-Identifier: GPL-3.0-or-later

const GLPI_VERSION = '';

class CommonITILActor
{
   public const REQUESTER = 1;
   public const ASSIGN = 2;
   public const OBSERVER = 3;
}

class Ticket
{
    /** @var array<string, mixed> */
   public array $fields;

   public function isNewItem(): bool {
   }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
   public function getActorsForType(int $actorType = 1, array $params = []): array {
   }

   public function getID(): int {
   }
}

class RuleCriteria
{
    /** @var array<string, mixed> */
   public array $fields;
}

class DBmysql
{
    /**
     * @param array<string, mixed> $criteria
     * @return iterable<array<string, mixed>>
     */
   public function request(array $criteria): iterable {
   }
}

class RuleAction
{
    /** @var array<string, mixed> */
   public array $fields;

   public function getTable(): string {
   }

    /** @return list<RuleAction> */
   public function getRuleActions(int $ruleId): array {
   }
}

class RuleTicket
{
   public const ONADD = 1;
   public const ONUPDATE = 2;

    /** @var array<string, mixed> */
   public array $fields;

    /** @var list<RuleCriteria> */
   public array $criterias;

    /** @return array<string, array<string, mixed>> */
   public function getCriterias(): array {
   }

    /**
     * @param array<string, mixed> $input
     * @param array<int, array<string, mixed>> $checkResults
     */
   public function testCriterias(array $input, array &$checkResults): void {
   }

    /** @param array<string, mixed> $input */
   public function checkCriterias(array $input): bool {
   }
}

class SingletonRuleList
{
    /** @var list<RuleTicket> */
   public array $list;
}

class RuleTicketCollection
{
   public ?SingletonRuleList $RuleList;

   public function __construct(int $entity = 0) {
   }

   public function getCollectionDatas(int $retrieveCriteria, int $retrieveAction, int $condition): void {
   }
}

class ITILCategory
{
    /** @var array<string, mixed> */
   public array $fields;

   public static function getById(int $id): static|false {
   }
}

class Group_User
{
    /** @return list<array<string, mixed>> */
   public static function getUserGroups(int $usersId): array {
   }
}

class User
{
    /** @var array<string, mixed> */
   public array $fields;

   public function getFromDB(int $id): bool {
   }
}
