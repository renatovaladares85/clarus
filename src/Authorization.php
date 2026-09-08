<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

final class Authorization
{
   public static function canInspectTicket(\Ticket $ticket): bool {
       return \Session::haveRight(Profile::RIGHT_INSPECT, READ)
           && $ticket->canViewItem();
   }
}
