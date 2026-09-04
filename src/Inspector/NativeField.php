<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Normalizes values returned by GLPI's untyped public fields arrays. */
final class NativeField
{
   public static function integer(mixed $value): int {
      if (is_int($value)) {
         return $value;
      }
      if (is_string($value) && is_numeric($value)) {
         return (int) $value;
      }

      return 0;
   }

   public static function string(mixed $value): string {
      if (is_string($value)) {
         return $value;
      }
      if (is_int($value) || is_float($value)) {
         return (string) $value;
      }

      return '';
   }

   public static function boolean(mixed $value): bool {
      if (is_bool($value)) {
         return $value;
      }

      return self::integer($value) !== 0;
   }
}
