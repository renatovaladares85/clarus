<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** A persisted later change is factual, but its RuleTicket cause may be unknown. */
enum LaterChangeClassification: string
{
   case PERSISTED_LATER_CHANGE = 'PERSISTED_LATER_CHANGE';
   case NOT_ATTRIBUTABLE_TO_RULE = 'NOT_ATTRIBUTABLE_TO_RULE';
   case POSSIBLE_LATER_RULE_EXECUTION = 'POSSIBLE_LATER_RULE_EXECUTION';
}
