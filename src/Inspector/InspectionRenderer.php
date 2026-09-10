<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Inspector;

use Glpi\Application\View\TemplateRenderer;

/** Renders the safe inspection view model through GLPI's Twig environment. */
final class InspectionRenderer
{
   public function __construct(private readonly InspectionPresenter $presenter = new InspectionPresenter()) {
   }

   /**
    * @param list<InspectionResult> $results
    * @param array<string, bool|int|string|list<array{field: string, direction: string}>> $settings
    */
   public function render(
       array $results,
       array $settings,
       int $ticketId,
       string $refreshUrl,
       string $csrfToken,
       bool $loaded = true,
       ?string $error = null,
       bool $canViewSensitiveValues = false
   ): string {
       return TemplateRenderer::getInstance()->render('@clarus/inspection.html.twig', $this->presenter->present(
           $results,
           $settings,
           $ticketId,
           $refreshUrl,
           $csrfToken,
           $loaded,
           $error,
           $canViewSensitiveValues
       ));
   }
}
