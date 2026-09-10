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

      echo self::renderInspection($item);

      return true;
   }

   public static function renderInspection(\Ticket $ticket, bool $force = false): string {
      if ($ticket->isNewItem() || !Authorization::canInspectTicket($ticket)) {
          return '';
      }

       $settings = ClarusConfig::get();
       $loaded = $force || $settings[ClarusConfig::AUTO_INSPECTION];
       $results = [];
       $error = null;
      if ($loaded) {
         try {
             $options = new InspectionOptions(
                 $settings[ClarusConfig::RULE_LIMIT],
                 $settings[ClarusConfig::INCLUDE_ACTIONS]
             );
             $inspector = new RuleTicketInspector();
            if ($settings[ClarusConfig::INCLUDE_ONADD]) {
                $results[] = $inspector->inspect($ticket, \RuleTicket::ONADD, $options);
            }
            if ($settings[ClarusConfig::INCLUDE_ONUPDATE]) {
                $results[] = $inspector->inspect($ticket, \RuleTicket::ONUPDATE, $options);
            }
         } catch (\Throwable $exception) {
             self::logFailure($exception);
             $results = [];
             $error = __('The inspection could not be completed. Try again or contact an administrator.', 'clarus');
         }
      }

       $pluginWebDir = \Plugin::getWebDir('clarus');
       $refreshUrl = (is_string($pluginWebDir) ? rtrim($pluginWebDir, '/') : '/plugins/clarus')
           . '/ajax/inspection.php';

       return (new InspectionRenderer())->render(
           $results,
           $settings,
           $ticket->getID(),
           $refreshUrl,
           \Session::getNewCSRFToken(),
           $loaded,
           $error
       );
   }

   private static function logFailure(\Throwable $exception): void {
       \Toolbox::logInFile(
           'php-errors',
           sprintf("Clarus rule inspection failed: %s\n", $exception->getMessage())
       );
   }
}
