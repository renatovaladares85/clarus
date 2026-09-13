<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Internal, immutable trace for one reconstructed RuleTicket execution window. */
final class RuleTicketReplay
{
   /**
    * @param list<RuleInspection> $rules
    * @param list<string> $limitations
    * @param list<RuleOverwrite> $overwrites
    * @param list<LaterTicketChange> $laterChanges
    */
   public function __construct(
       public readonly ExecutionWindow $window,
       public readonly array $rules,
       public readonly array $limitations,
       public readonly array $overwrites,
       public readonly array $laterChanges = []
   ) {
   }

   public function rule(int $ruleId): ?RuleInspection {
      foreach ($this->rules as $rule) {
         if ($rule->id === $ruleId) {
             return $rule;
         }
      }

       return null;
   }

   /** @param list<LaterTicketChange> $laterChanges */
   public function withLaterChanges(array $laterChanges): self {
       return new self($this->window, $this->rules, $this->limitations, $this->overwrites, $laterChanges);
   }
}
