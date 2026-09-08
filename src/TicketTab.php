<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use GlpiPlugin\Clarus\Inspector\InspectionRenderer;
use GlpiPlugin\Clarus\Inspector\RuleTicketInspector;

final class TicketTab extends \CommonDBTM
{
   /** @var string */
   public static $rightname = 'ticket';

   /** @param int $withtemplate */
   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string {
      if (!$item instanceof \Ticket || $item->isNewItem() || !Authorization::canInspectTicket($item)) {
         return '';
      }

      return self::createTabEntry(__('Rule inspection', 'clarus'));
   }

   /**
    * @param int $tabnum
    * @param int $withtemplate
    */
   public static function displayTabContentForItem(
      \CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {
      if (!$item instanceof \Ticket || $item->isNewItem() || !Authorization::canInspectTicket($item)) {
         return false;
      }

      $inspector = new RuleTicketInspector();
      $renderer = new InspectionRenderer();
      $options = new InspectionOptions(InspectionOptions::DEFAULT_LIMIT, true);

      echo $renderer->render($inspector->inspect($item, \RuleTicket::ONADD, $options));
      echo $renderer->render($inspector->inspect($item, \RuleTicket::ONUPDATE, $options));

      return true;
   }
}
