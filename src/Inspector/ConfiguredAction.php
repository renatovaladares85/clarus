<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Internal immutable representation of one persisted RuleAction row. */
final class ConfiguredAction
{
   public function __construct(
        public readonly int $ruleId,
        public readonly int $actionId,
        public readonly string $actionType,
        public readonly string $field,
        public readonly mixed $configuredValue,
        public readonly int $order
    ) {
   }
}
