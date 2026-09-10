<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

use GlpiPlugin\Clarus\Inspector\InspectionRenderer;

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

   public static function renderInspection(
      \Ticket $ticket,
      bool $force = false,
      ?TicketInspection $inspection = null
   ): string {
      if ($ticket->isNewItem() || !Authorization::canInspectTicket($ticket)) {
          return '';
      }

      $settings = ClarusConfig::get();
      $loaded = $force || $settings[ClarusConfig::AUTO_INSPECTION];
      $results = [];
      $error = null;
      if ($loaded) {
         try {
            $results = ($inspection ?? new TicketInspection())->inspect($ticket, $settings);
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
           $error,
           Authorization::canViewSensitiveInspectionValues()
       );
   }

   private static function logFailure(\Throwable $exception): void {
       \Toolbox::logInFile(
           'php-errors',
           sprintf("Clarus rule inspection failed: %s\n", $exception->getMessage())
       );
   }
}
