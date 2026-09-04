<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

enum ContextState: string
{
    case AVAILABLE = 'AVAILABLE';
    case INDETERMINATE = 'INDETERMINATE';
}
