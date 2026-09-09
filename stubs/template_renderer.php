<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Glpi\Application\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TemplateRenderer
{
   private static ?self $instance = null;

   private Environment $environment;

   private function __construct() {
       $this->environment = new Environment(new FilesystemLoader(dirname(__DIR__) . '/templates'));
   }

   public static function getInstance(): self {
       return self::$instance ??= new self();
   }

   /** @param array<string, mixed> $variables */
   public function render(string $template, array $variables = []): string {
       $template = str_starts_with($template, '@clarus/') ? substr($template, 8) : $template;

       return $this->environment->render($template, $variables);
   }

   /** @param array<string, mixed> $variables */
   public function display(string $template, array $variables = []): void {
       echo $this->render($template, $variables);
   }
}
