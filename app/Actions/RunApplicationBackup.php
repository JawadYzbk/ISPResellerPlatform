<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final readonly class RunApplicationBackup implements Action
{
    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run', [
            '--disable-notifications' => true,
            '--isolated' => true,
            '--quiet' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('The backup command did not complete successfully.');
        }
    }
}
