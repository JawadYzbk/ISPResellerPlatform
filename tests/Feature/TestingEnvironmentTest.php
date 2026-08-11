<?php

it('keeps feature tests on an isolated sqlite database', function (): void {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(app()->environment())->toBe('testing');
});
