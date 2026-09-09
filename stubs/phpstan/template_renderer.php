<?php

namespace Glpi\Application\View;

class TemplateRenderer
{
    public static function getInstance(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $variables */
    public function render(string $template, array $variables = []): string
    {
        return '';
    }

    /** @param array<string, mixed> $variables */
    public function display(string $template, array $variables = []): void
    {
    }
}
