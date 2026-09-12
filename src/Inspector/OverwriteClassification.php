<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

/** Classification of an overwrite diagnostic, independent of rule evaluation. */
enum OverwriteClassification: string
{
   case POSSIBLE = 'POSSIBLE';
   case CONFIRMED = 'CONFIRMED';
}
