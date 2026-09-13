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
    * @param list<string> $replayLimitations
    * @param array<string, RuleInspection> $replayContexts
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
        public readonly array $actions = [],
        public readonly ?SequentialRuleStep $sequentialStep = null,
        public readonly ?Evaluation $replayEvaluation = null,
        public readonly array $replayLimitations = [],
        public readonly array $replayContexts = []
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
           $actions,
           $this->sequentialStep,
           $this->replayEvaluation,
           $this->replayLimitations,
           $this->replayContexts
       );
   }

   public function withSequentialStep(SequentialRuleStep $step): self {
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
           $this->actions,
           $step,
           $this->replayEvaluation,
           $this->replayLimitations,
           $this->replayContexts
       );
   }

   public function withReplay(?RuleInspection $replayRule): self {
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
           $this->actions,
           $replayRule?->sequentialStep,
           $replayRule?->evaluation,
           $replayRule === null ? [] : $replayRule->limitations,
           $this->replayContexts
       );
   }

   /** @param array<string, RuleInspection> $replays */
   public function withReplayContexts(array $replays): self {
       $primary = reset($replays);
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
           $this->actions,
           $primary instanceof self ? $primary->sequentialStep : null,
           $primary instanceof self ? $primary->evaluation : null,
           $primary instanceof self ? $primary->limitations : [],
           $replays
       );
   }
}
