<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

enum ActionEvaluation: string
{
   case REFLECTED = 'REFLECTED';
   case NOT_REFLECTED = 'NOT_REFLECTED';
   case INDETERMINATE = 'INDETERMINATE';
}
