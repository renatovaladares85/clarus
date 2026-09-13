<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** A persisted later change is factual, but its RuleTicket cause may be unknown. */
enum LaterChangeClassification: string
{
   case NOT_ATTRIBUTABLE_TO_RULE = 'NOT_ATTRIBUTABLE_TO_RULE';
}
