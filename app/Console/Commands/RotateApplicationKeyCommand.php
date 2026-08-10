<?php

namespace App\Console\Commands;

use App\Actions\RotateApplicationKey;
use Illuminate\Console\Command;
use Throwable;

final class RotateApplicationKeyCommand extends Command
{
    protected $signature = 'security:rotate-app-key {--new-key= : New APP_KEY value; generated when omitted} {--old-key= : Current APP_KEY when it differs from the loaded application key}';

    protected $description = 'Re-encrypt application secrets with a replacement APP_KEY in one transaction.';

    public function handle(RotateApplicationKey $rotate): int
    {
        $oldKey = (string) ($this->option('old-key') ?: config('app.key'));
        $newKey = (string) ($this->option('new-key') ?: 'base64:'.base64_encode(random_bytes(32)));

        try {
            $result = $rotate->handle($oldKey, $newKey);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Re-encrypted '.$result['records'].' secret record(s).');
        $this->line('Update APP_KEY to the following value before the next request:');
        $this->line($result['new_key']);

        return self::SUCCESS;
    }
}
