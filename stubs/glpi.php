<?php
// SPDX-License-Identifier: GPL-3.0-or-later

const GLPI_VERSION = '';
const READ = 1;
const UPDATE = 2;
const CREATE = 4;
const PURGE = 16;

function __(string $string, ?string $domain = null): string
{
    return $GLOBALS['clarusTranslations'][$domain][$string] ?? $string;
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

    public function getFromDB(int $id): bool
    {
        $this->fields['id'] = $id;
        return $id > 0;
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

    public static function getNewCSRFToken(): string
    {
        return 'csrf-token';
    }

    /** @param array<string, mixed> $data */
    public static function checkCSRF(array $data): void
    {
    }

    public static function checkRight(string $module, int $right): void
    {
    }

    public static function addMessageAfterRedirect(string $message): void
    {
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

    /** @return array<string, mixed>|null */
    public static function registeredAttributes(string $itemtype): ?array
    {
        return self::$registeredClasses[$itemtype] ?? null;
    }

    public static function getWebDir(string $pluginKey = '', bool $full = true): string|false
    {
        return '/plugins/' . $pluginKey;
    }

    public static function load(string $pluginKey, bool $checkPrerequisites = false): bool
    {
        return true;
    }
}

class Config
{
    /** @var array<string, array<string, mixed>> */
    public static array $values = [];

    /** @param list<string> $names
     *  @return array<string, mixed>
     */
    public static function getConfigurationValues(string $context, array $names = []): array
    {
        $values = self::$values[$context] ?? [];
        if ($names === []) {
            return $values;
        }

        return array_intersect_key($values, array_fill_keys($names, true));
    }

    /** @param array<string, mixed> $values */
    public static function setConfigurationValues(string $context, array $values = []): void
    {
        self::$values[$context] = array_replace(self::$values[$context] ?? [], $values);
    }

    /** @param list<string> $names */
    public static function deleteConfigurationValues(string $context, array $names = []): void
    {
        foreach ($names as $name) {
            unset(self::$values[$context][$name]);
        }
    }
}

class Entity extends CommonDBTM
{
    public static function getTable(): string
    {
        return 'glpi_entities';
    }
}

class Dropdown
{
    public static function getDropdownName(string $table, int $id): string
    {
        return $id === 0 ? 'Root entity' : 'Entity #' . $id;
    }
}

class Toolbox
{
    public static function logInFile(string $name, string $text, bool $force = false): bool
    {
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

    public static function header(string $title, string $url, string $sector = '', string $item = ''): void
    {
    }

    public static function footer(): void
    {
    }

    public static function redirect(string $url): void
    {
    }
}

class CommonITILActor
{
   public const REQUESTER = 1;
   public const ASSIGN = 2;
   public const OBSERVER = 3;
}

class Ticket extends CommonDBTM
{
    /** @var array<string, mixed> */
    public array $fields;

   public bool $viewable = false;

   public function isNewItem(): bool {
       return $this->getID() <= 0;
   }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
   public function getActorsForType(int $actorType = 1, array $params = []): array {
       return [];
   }

   public function getID(): int {
       return parent::getID();
   }

   public function canViewItem(): bool {
       return $this->viewable;
   }
}

class RuleCriteria
{
    /** @var array<string, mixed> */
   public array $fields;

   public static function getConditionByID(int $condition, string $itemtype, string $criterion): string
   {
       return $condition === 2 ? 'contains' : '';
   }
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

   public static function getActionByID(string $actionType): string
   {
       return $actionType === 'assign' ? 'Assign' : '';
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

   /** @return array<string, array{name: string}> */
   public function getCriterias(): array
   {
       return ['content' => ['name' => 'Description']];
   }

   /** @return array<string, array{name: string}> */
   public function getActions(): array
   {
       return ['urgency' => ['name' => 'Urgency']];
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
