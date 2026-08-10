<?php

use Illuminate\Routing\Route as LaravelRoute;

it('keeps the versioned API route inventory aligned with the OpenAPI document', function (): void {
    $documented = documentedOpenApiOperations();
    $actual = [];

    /** @var LaravelRoute $route */
    foreach (app('router')->getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/v1/')) {
            continue;
        }

        $path = normalizeOpenApiPath('/'.substr($uri, strlen('api/v1/')));
        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $actual[$path][] = strtolower($method);
        }
    }

    $actual = normalizeOperations($actual);
    $documented = normalizeOperations($documented);

    expect(array_diff_key($actual, $documented))->toBe([])
        ->and(array_diff_key($documented, $actual))->toBe([]);

    foreach ($actual as $path => $methods) {
        expect(array_diff($methods, $documented[$path] ?? []))->toBe([], $path.' has undocumented operations')
            ->and(array_diff($documented[$path] ?? [], $methods))->toBe([], $path.' documents a missing operation');
    }
});

/** @return array<string, list<string>> */
function documentedOpenApiOperations(): array
{
    $documented = [];
    $path = null;

    foreach (preg_split('/\R/', (string) file_get_contents(base_path('openapi/isp-platform-v1.yaml'))) as $line) {
        if ($line === 'components:') {
            break;
        }

        if (preg_match('/^  (\/[^:]+):$/', $line, $matches) === 1) {
            $path = $matches[1];
            $documented[$path] ??= [];

            continue;
        }

        if ($path !== null && preg_match('/^    (get|post|put|patch|delete|head|options):$/', $line, $matches) === 1) {
            $documented[$path][] = $matches[1];
        }
    }

    return $documented;
}

/** @param array<string, list<string>> $operations @return array<string, list<string>> */
function normalizeOperations(array $operations): array
{
    foreach ($operations as $path => $methods) {
        sort($methods);
        $operations[$path] = array_values(array_unique($methods));
    }
    ksort($operations);

    return $operations;
}

function normalizeOpenApiPath(string $path): string
{
    return (string) preg_replace_callback('/\{([^}]+)\}/', static function (array $matches): string {
        $parts = explode(':', $matches[1], 2);

        return '{'.($parts[1] ?? $parts[0]).'}';
    }, $path);
}
