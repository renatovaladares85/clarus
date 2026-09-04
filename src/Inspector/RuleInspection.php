<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class RuleInspection
{
    /**
     * @param list<CriterionInspection> $criteria
     * @param list<string> $limitations
     * @param list<ActionInspection> $actions
     */
   public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $condition,
        public readonly int $entityId,
        public readonly bool $recursive,
        public readonly int $ranking,
        public readonly string $matchingMode,
        public readonly array $criteria,
        public readonly Evaluation $evaluation,
        public readonly array $limitations = [],
        public readonly array $actions = []
    ) {
   }

    /** @param list<ActionInspection> $actions */
   public function withActions(array $actions): self {
       return new self(
           $this->id,
           $this->name,
           $this->condition,
           $this->entityId,
           $this->recursive,
           $this->ranking,
           $this->matchingMode,
           $this->criteria,
           $this->evaluation,
           $this->limitations,
           $actions
       );
   }
}
