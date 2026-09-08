<?php
// SPDX-License-Identifier: GPL-3.0-or-later

const GLPI_VERSION = '';
const READ = 1;
const UPDATE = 2;
const CREATE = 4;
const PURGE = 16;

function __(string $string, ?string $domain = null): string
{
    return $string;
}

function _sx(string $context, string $string): string
{
    return $string;
}

class CommonGLPI
{
    /** @var array<string, mixed> */
    public array $fields = [];

    public function getField(string $field): mixed
    {
        return $this->fields[$field] ?? null;
    }

    public function getID(): int
    {
        return (int) ($this->fields['id'] ?? 0);
    }
}

class CommonDBTM extends CommonGLPI
{
    /** @var string */
    public static $rightname = '';

    public static function canUpdate(): bool
    {
        return true;
    }

    public static function createTabEntry(string $label): string
    {
        return $label;
    }

    public function getFormURL(): string
    {
        return '/front/profile.form.php';
    }

    /**
     * @param array<int, array<string, mixed>> $rights
     * @param array<string, mixed> $options
     */
    public function displayRightsChoiceMatrix(array $rights, array $options = []): void
    {
    }
}

class Profile extends CommonDBTM
{
}

class ProfileRight
{
    /** @var array<string, mixed> */
    public static array $possibleRights = [];

    /** @return array<string, mixed> */
    public static function getAllPossibleRights(): array
    {
        return self::$possibleRights;
    }

    /** @param list<string> $rights */
    public static function addProfileRights(array $rights): bool
    {
        foreach ($rights as $right) {
            self::$possibleRights[$right] = '';
        }

        return true;
    }

    /** @param list<string> $rights */
    public static function deleteProfileRights(array $rights): bool
    {
        foreach ($rights as $right) {
            unset(self::$possibleRights[$right]);
        }

        return true;
    }
}

class Session
{
    public static bool $hasRight = false;

    public static function haveRight(string $module, int $right): bool
    {
        return self::$hasRight;
    }
}

class Plugin
{
    /** @var array<class-string, array<string, mixed>> */
    public static array $registeredClasses = [];

    /**
     * @param class-string $itemtype
     * @param array<string, mixed> $attributes
     */
    public static function registerClass(string $itemtype, array $attributes = []): bool
    {
        self::$registeredClasses[$itemtype] = $attributes;
        return true;
    }
}

class Html
{
    /** @param array<string, mixed> $options */
    public static function submit(string $caption, array $options = []): string
    {
        return $caption;
    }

    public static function closeForm(): bool
    {
        return true;
    }
}

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

    public bool $viewable = false;

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

   public function canViewItem(): bool {
       return $this->viewable;
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
