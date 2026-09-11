<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

enum ProjectionStatus: string
{
   case APPLIED = 'APPLIED';
   case NOT_APPLIED = 'NOT_APPLIED';
   case INDETERMINATE = 'INDETERMINATE';
   case UNSUPPORTED = 'UNSUPPORTED';
}
