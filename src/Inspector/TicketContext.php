<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

final class TicketContext
{
   /** @param array<string, ContextValue> $values */
   public function __construct(private readonly array $values) {
   }

   public function get(string $key): ContextValue {
       return $this->values[$key]
           ?? ContextValue::indeterminate('Criterion is not part of the reconstructable Ticket context.');
   }

    /** @return array<string, mixed> */
   public function availableInput(): array {
       $input = [];
      foreach ($this->values as $key => $contextValue) {
         if ($contextValue->state === ContextState::AVAILABLE) {
            $input[$key] = $contextValue->value;
         }
      }

       return $input;
   }

    /** @return array<string, ContextValue> */
   public function values(): array {
       return $this->values;
   }

   public function with(string $key, ContextValue $value): self {
       $values = $this->values;
       $values[$key] = $value;

       return new self($values);
   }

   /**
    * Keeps the known RuleTicket keys but deliberately removes their values.
    *
    * A saved Ticket is evidence of its current state, not automatically of
    * the input supplied to a past Rules Engine execution.
    */
   public function withoutValues(string $reason): self {
       $values = [];
      foreach ($this->values as $key => $unused) {
          $values[$key] = ContextValue::indeterminate($reason);
      }

       return new self($values);
   }
}
