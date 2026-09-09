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
   public const MAX_RULE_LIMIT = 5000;

   /** @var list<int> */
   public const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

   /** @var list<string> */
   public const GROUP_OPTIONS = ['processing', 'result', 'entity'];

   /**
    * @return array{
    *     inspection_auto: bool,
    *     inspection_onadd: bool,
    *     inspection_onupdate: bool,
    *     inspection_actions: bool,
    *     inspection_rule_limit: int,
    *     inspection_page_size: int,
    *     inspection_initial_group: string
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
    *     inspection_initial_group: string
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
