<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Evidence strength for a replay window, never a claim of historical causality. */
enum ReplayEvidenceLevel: string
{
   case EXACT = 'EXACT';
   case POSSIBLE_REPLAY = 'POSSIBLE_REPLAY';
   case INDETERMINATE = 'INDETERMINATE';
}
