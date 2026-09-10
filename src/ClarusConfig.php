<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

final class ClarusConfig
{
   public const CONTEXT = 'plugin:clarus';
   public const AUTO_INSPECTION = 'inspection_auto';
   public const INCLUDE_ONADD = 'inspection_onadd';
   public const INCLUDE_ONUPDATE = 'inspection_onupdate';
   public const INCLUDE_ACTIONS = 'inspection_actions';
   public const RULE_LIMIT = 'inspection_rule_limit';
   public const PAGE_SIZE = 'inspection_page_size';
   public const INITIAL_GROUP = 'inspection_initial_group';
   public const MIN_ADHERENCE = 'inspection_min_adherence';
   public const INITIAL_SORT = 'inspection_initial_sort';
   public const MAX_RULE_LIMIT = 5000;
   public const MAX_SORT_LEVELS = 3;

   /** @var list<int> */
   public const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

   /** @var list<string> */
   public const GROUP_OPTIONS = ['processing', 'result', 'entity'];

   /** @var list<string> */
   public const SORT_FIELDS = [
       'result', 'adherence', 'matches', 'criteria', 'indeterminate', 'ranking',
       'entity', 'condition', 'actions', 'name', 'id',
   ];

   /** @var list<string> */
   public const SORT_DIRECTIONS = ['asc', 'desc'];

   /**
    * @return array{
    *     inspection_auto: bool,
    *     inspection_onadd: bool,
    *     inspection_onupdate: bool,
    *     inspection_actions: bool,
    *     inspection_rule_limit: int,
    *     inspection_page_size: int,
    *     inspection_initial_group: string,
    *     inspection_min_adherence: int,
    *     inspection_initial_sort: list<array{field: string, direction: string}>
    * }
    */
   public static function get(): array {
       $defaults = self::defaults();
       $stored = \Config::getConfigurationValues(self::CONTEXT, array_keys($defaults));
       $values = array_replace($defaults, $stored);

       return [
           self::AUTO_INSPECTION => self::storedBoolean(
               $values[self::AUTO_INSPECTION],
               $defaults[self::AUTO_INSPECTION]
           ),
           self::INCLUDE_ONADD => self::storedBoolean(
               $values[self::INCLUDE_ONADD],
               $defaults[self::INCLUDE_ONADD]
           ),
           self::INCLUDE_ONUPDATE => self::storedBoolean(
               $values[self::INCLUDE_ONUPDATE],
               $defaults[self::INCLUDE_ONUPDATE]
           ),
           self::INCLUDE_ACTIONS => self::storedBoolean(
               $values[self::INCLUDE_ACTIONS],
               $defaults[self::INCLUDE_ACTIONS]
           ),
           self::RULE_LIMIT => self::storedRuleLimit($values[self::RULE_LIMIT]),
           self::PAGE_SIZE => self::storedPageSize($values[self::PAGE_SIZE]),
           self::INITIAL_GROUP => self::storedGroup($values[self::INITIAL_GROUP]),
           self::MIN_ADHERENCE => self::storedAdherence($values[self::MIN_ADHERENCE]),
           self::INITIAL_SORT => self::storedSort($values[self::INITIAL_SORT]),
       ];
   }

   public static function installDefaults(): void {
       $defaults = self::defaults();
       $stored = \Config::getConfigurationValues(self::CONTEXT, array_keys($defaults));
       $missing = array_diff_key($defaults, $stored);

      if ($missing !== []) {
          \Config::setConfigurationValues(self::CONTEXT, $missing);
      }
   }

   /** @param array<string, mixed> $input */
   public static function update(array $input): void {
       $values = [
           self::AUTO_INSPECTION => self::inputBoolean($input, self::AUTO_INSPECTION),
           self::INCLUDE_ONADD => self::inputBoolean($input, self::INCLUDE_ONADD),
           self::INCLUDE_ONUPDATE => self::inputBoolean($input, self::INCLUDE_ONUPDATE),
           self::INCLUDE_ACTIONS => self::inputBoolean($input, self::INCLUDE_ACTIONS),
           self::RULE_LIMIT => self::inputRuleLimit($input[self::RULE_LIMIT] ?? null),
           self::PAGE_SIZE => self::inputPageSize($input[self::PAGE_SIZE] ?? null),
           self::INITIAL_GROUP => self::inputGroup($input[self::INITIAL_GROUP] ?? null),
           self::MIN_ADHERENCE => self::inputAdherence($input[self::MIN_ADHERENCE] ?? null),
           self::INITIAL_SORT => json_encode(self::inputSort($input), JSON_THROW_ON_ERROR),
       ];

       \Config::setConfigurationValues(self::CONTEXT, $values);
   }

   public static function remove(): void {
       \Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::defaults()));
   }

   /**
    * @return array{
    *     inspection_auto: bool,
    *     inspection_onadd: bool,
    *     inspection_onupdate: bool,
    *     inspection_actions: bool,
    *     inspection_rule_limit: int,
    *     inspection_page_size: int,
    *     inspection_initial_group: string,
    *     inspection_min_adherence: int,
    *     inspection_initial_sort: string
    * }
    */
   public static function defaults(): array {
       return [
           self::AUTO_INSPECTION => true,
           self::INCLUDE_ONADD => true,
           self::INCLUDE_ONUPDATE => true,
           self::INCLUDE_ACTIONS => true,
           self::RULE_LIMIT => 1000,
           self::PAGE_SIZE => 25,
           self::INITIAL_GROUP => 'processing',
           self::MIN_ADHERENCE => 80,
           self::INITIAL_SORT => '[{"field":"adherence","direction":"desc"},{"field":"ranking","direction":"asc"}]',
       ];
   }

   private static function storedBoolean(mixed $value, bool $default): bool {
      if (in_array($value, [true, 1, '1'], true)) {
          return true;
      }
      if (in_array($value, [false, 0, '0'], true)) {
          return false;
      }

       return $default;
   }

   private static function storedRuleLimit(mixed $value): int {
       $limit = self::integer($value);

       return $limit !== null && $limit >= 1 && $limit <= self::MAX_RULE_LIMIT ? $limit : 1000;
   }

   private static function storedPageSize(mixed $value): int {
       $pageSize = self::integer($value);

       return $pageSize !== null && in_array($pageSize, self::PAGE_SIZE_OPTIONS, true) ? $pageSize : 25;
   }

   private static function storedGroup(mixed $value): string {
       return is_string($value) && in_array($value, self::GROUP_OPTIONS, true) ? $value : 'processing';
   }

   private static function storedAdherence(mixed $value): int {
       $adherence = self::integer($value);

       return $adherence !== null && $adherence >= 0 && $adherence <= 100 ? $adherence : 80;
   }

   /** @return list<array{field: string, direction: string}> */
   private static function storedSort(mixed $value): array {
      if (!is_string($value)) {
          return self::defaultSort();
      }

      try {
          $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
      } catch (\JsonException) {
          return self::defaultSort();
      }

       return self::validatedSort($decoded) ?? self::defaultSort();
   }

   /** @param array<string, mixed> $input */
   private static function inputBoolean(array $input, string $key): bool {
       $value = $input[$key] ?? null;
      if (in_array($value, [true, 1, '1'], true)) {
          return true;
      }
      if (in_array($value, [false, 0, '0'], true)) {
          return false;
      }

       throw new \InvalidArgumentException('Invalid boolean configuration value.');
   }

   private static function inputRuleLimit(mixed $value): int {
       $limit = self::integer($value);
      if ($limit === null || $limit < 1 || $limit > self::MAX_RULE_LIMIT) {
          throw new \InvalidArgumentException('Invalid rule inspection limit.');
      }

       return $limit;
   }

   private static function inputPageSize(mixed $value): int {
       $pageSize = self::integer($value);
      if ($pageSize === null || !in_array($pageSize, self::PAGE_SIZE_OPTIONS, true)) {
          throw new \InvalidArgumentException('Invalid inspection page size.');
      }

       return $pageSize;
   }

   private static function inputGroup(mixed $value): string {
      if (!is_string($value) || !in_array($value, self::GROUP_OPTIONS, true)) {
          throw new \InvalidArgumentException('Invalid inspection grouping.');
      }

       return $value;
   }

   private static function inputAdherence(mixed $value): int {
       $adherence = self::integer($value);
      if ($adherence === null || $adherence < 0 || $adherence > 100) {
          throw new \InvalidArgumentException('Invalid minimum adherence.');
      }

       return $adherence;
   }

   /**
    * @param array<string, mixed> $input
    * @return list<array{field: string, direction: string}>
    */
   private static function inputSort(array $input): array {
       $sort = [];
      for ($level = 1; $level <= self::MAX_SORT_LEVELS; ++$level) {
          $field = $input['inspection_sort_' . $level . '_field'] ?? null;
          $direction = $input['inspection_sort_' . $level . '_direction'] ?? null;
         if (!is_string($direction) || !in_array($direction, self::SORT_DIRECTIONS, true)) {
             throw new \InvalidArgumentException('Invalid inspection sorting.');
         }
         if ($field === '') {
             continue;
         }
         if (!is_string($field) || !in_array($field, self::SORT_FIELDS, true)) {
             throw new \InvalidArgumentException('Invalid inspection sorting.');
         }
          $sort[] = ['field' => $field, 'direction' => $direction];
      }

       $validated = self::validatedSort($sort);
      if ($validated === null) {
          throw new \InvalidArgumentException('Invalid inspection sorting.');
      }

       return $validated;
   }

   /** @return list<array{field: string, direction: string}> */
   private static function defaultSort(): array {
       return [
           ['field' => 'adherence', 'direction' => 'desc'],
           ['field' => 'ranking', 'direction' => 'asc'],
       ];
   }

   /** @return null|list<array{field: string, direction: string}> */
   private static function validatedSort(mixed $sort): ?array {
      if (!is_array($sort) || $sort === [] || count($sort) > self::MAX_SORT_LEVELS) {
          return null;
      }

       $seen = [];
       $validated = [];
      foreach ($sort as $entry) {
         if (!is_array($entry) || !isset($entry['field'], $entry['direction'])
             || !is_string($entry['field']) || !is_string($entry['direction'])
             || !in_array($entry['field'], self::SORT_FIELDS, true)
             || !in_array($entry['direction'], self::SORT_DIRECTIONS, true)
             || isset($seen[$entry['field']])) {
             return null;
         }
          $seen[$entry['field']] = true;
          $validated[] = ['field' => $entry['field'], 'direction' => $entry['direction']];
      }

       return $validated;
   }

   private static function integer(mixed $value): ?int {
      if (is_int($value)) {
          return $value;
      }
      if (is_string($value) && preg_match('/^\d+$/D', $value) === 1) {
          return (int) $value;
      }

       return null;
   }
}
