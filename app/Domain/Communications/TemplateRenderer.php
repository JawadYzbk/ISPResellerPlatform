<?php

namespace App\Domain\Communications;

use App\Models\MessageTemplate;
use RuntimeException;

final class TemplateRenderer
{
    /** @param array<string, scalar|null> $variables */
    public function render(MessageTemplate $template, array $variables, bool $preview = false): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $matches) use ($variables, $preview): string {
            $key = $matches[1];
            if (! array_key_exists($key, $variables)) {
                if ($preview) {
                    throw new RuntimeException("Missing template variable [{$key}].");
                }

                return '';
            }

            return (string) ($variables[$key] ?? '');
        }, $template->body) ?? $template->body;
    }
}
