<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

enum Evaluation: string
{
    case MATCH = 'MATCH';
    case NO_MATCH = 'NO_MATCH';
    case INDETERMINATE = 'INDETERMINATE';
}
