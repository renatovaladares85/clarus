<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

enum ActionSupport: string
{
   case SUPPORTED = 'SUPPORTED';
   case INDETERMINATE_BY_DESIGN = 'INDETERMINATE_BY_DESIGN';
   case UNSUPPORTED = 'UNSUPPORTED';
}
