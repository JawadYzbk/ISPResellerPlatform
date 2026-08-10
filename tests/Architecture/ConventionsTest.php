<?php

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\File;

it('requires tenant scoping on tenant-owned models', function (): void {
    $allowlist = ['App\\Models\\Tenant', 'App\\Models\\User'];

    foreach (File::allFiles(app_path('Models')) as $file) {
        $class = 'App\\Models\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname());
        if (in_array($class, $allowlist, true) || str_contains($class, '\\Concerns\\') || ! class_exists($class)) {
            continue;
        }

        expect(class_uses_recursive($class))->toContain(BelongsToTenant::class);
    }
});

it('requires final Actions with one public handle method', function (): void {
    foreach (File::allFiles(app_path('Actions')) as $file) {
        $class = 'App\\Actions\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname());
        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        expect($reflection->isFinal())->toBeTrue();
        expect($reflection->hasMethod('handle'))->toBeTrue();
        expect($reflection->getMethod('handle')->isPublic())->toBeTrue();
    }
});

it('keeps direct persistence and transaction calls out of controllers', function (): void {
    foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
        $contents = $file->getContents();

        expect($contents)->not->toMatch('/\\bDB::|::transaction\\(|::create\\(|->save\\(|->delete\\(/');
    }
});

it('keeps environment reads inside configuration files', function (): void {
    foreach (['app', 'bootstrap', 'database', 'routes'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            expect($file->getContents())->not->toMatch('/\\benv\\s*\\(/');
        }
    }
});

it('keeps Actions to one public handle method', function (): void {
    foreach (File::allFiles(app_path('Actions')) as $file) {
        $class = 'App\\Actions\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname());
        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $publicMethods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && ! $method->isConstructor() && ! $method->isDestructor())
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();

        expect($publicMethods)->toBe(['handle']);
    }
});

it('keeps money columns in integer or rational representations', function (): void {
    $moneyColumn = '/->(?:decimal|float)\\s*\\(\\s*[\'\"](?:[^\'\"]*(?:amount|balance|price|cost|fee|tax|rate)[^\'\"]*)[\'\"]/i';

    foreach (File::allFiles(database_path('migrations')) as $file) {
        expect($file->getContents())->not->toMatch($moneyColumn);
    }
});
