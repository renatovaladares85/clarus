<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus;

final class Profile extends \CommonDBTM
{
   public const RIGHT_INSPECT = 'plugin_clarus_inspect';

    /** @var string */
   public static $rightname = 'profile';

    /** @param int $nb */
   public static function getTypeName($nb = 0): string {
       return __('Clarus', 'clarus');
   }

    /** @param int $withtemplate */
   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string {
      if ($item instanceof \Profile && $item->getField('id')) {
          return self::createTabEntry(self::getTypeName());
      }

       return '';
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
      if ($item instanceof \Profile && $item->getField('id')) {
          self::showForProfile($item);
      }

       return true;
   }

    /**
     * @return list<array{itemtype: class-string, label: string, field: string, rights: array<int, string>}>
     */
   public static function getAllRights(): array {
       return [[
           'itemtype' => self::class,
           'label'    => __('Rule inspection', 'clarus'),
           'field'    => self::RIGHT_INSPECT,
           'rights'   => [READ => __('Read')],
       ]];
   }

   public static function registerRights(): bool {
      if (array_key_exists(self::RIGHT_INSPECT, \ProfileRight::getAllPossibleRights())) {
          return true;
      }

       return \ProfileRight::addProfileRights([self::RIGHT_INSPECT]);
   }

   public static function unregisterRights(): bool {
       return \ProfileRight::deleteProfileRights([self::RIGHT_INSPECT]);
   }

   private static function showForProfile(\Profile $profile): void {
       $canEdit = self::canUpdate();

       echo "<div class='spaced'>";
      if ($canEdit) {
          echo "<form method='post' action='" . $profile->getFormURL() . "' data-track-changes='true'>";
      }

       $profile->displayRightsChoiceMatrix(self::getAllRights(), [
           'canedit' => $canEdit,
           'title'   => self::getTypeName(),
       ]);

      if ($canEdit) {
          echo "<div class='center'>";
          echo "<input type='hidden' name='id' value='" . $profile->getID() . "'>";
          echo \Html::submit(
              "<i class='fas fa-save'></i><span>" . _sx('button', 'Save') . '</span>',
              [
                  'class' => 'btn btn-primary mt-2',
                  'name'  => 'update',
              ]
          );
          echo '</div>';
          \Html::closeForm();
      }
       echo '</div>';
   }
}
