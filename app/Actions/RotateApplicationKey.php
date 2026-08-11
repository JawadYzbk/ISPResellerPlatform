<?php

namespace App\Actions;

use App\Contracts\Action;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class RotateApplicationKey implements Action
{
    /** @return array{records: int, new_key: string} */
    public function handle(string $oldKey, string $newKey): array
    {
        $old = $this->encrypter($oldKey);
        $new = $this->encrypter($newKey);
        $records = 0;

        DB::transaction(function () use ($old, $new, &$records): void {
            foreach ($this->targets() as $target) {
                $rows = DB::table($target['table'])
                    ->whereNotNull($target['column'])
                    ->orderBy('id')
                    ->get(['id', $target['column']]);

                foreach ($rows as $row) {
                    $raw = (string) $row->{$target['column']};
                    try {
                        $plaintext = $old->decrypt($raw, false);
                    } catch (\Throwable $exception) {
                        throw new RuntimeException(sprintf('Unable to decrypt %s.%s row %s with the old key.', $target['table'], $target['column'], $row->id), previous: $exception);
                    }

                    $ciphertext = $target['json'] ? $new->encryptString($plaintext) : $new->encrypt($plaintext, false);
                    DB::table($target['table'])->where('id', $row->id)->update([$target['column'] => $ciphertext, 'updated_at' => now()]);
                    $records++;
                }
            }
        });

        return ['records' => $records, 'new_key' => $newKey];
    }

    private function encrypter(string $key): Encrypter
    {
        $normalized = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($normalized === false) {
            throw new InvalidArgumentException('Encryption keys must be valid base64: values or raw keys.');
        }

        return new Encrypter($normalized, (string) config('app.cipher', 'AES-256-CBC'));
    }

    /** @return list<array{table: string, column: string, json: bool}> */
    private function targets(): array
    {
        return [
            ['table' => 'services', 'column' => 'password_encrypted', 'json' => false],
            ['table' => 'routers', 'column' => 'password_encrypted', 'json' => false],
            ['table' => 'routers', 'column' => 'radius_secret_encrypted', 'json' => false],
            ['table' => 'radius_nas', 'column' => 'secret', 'json' => false],
            ['table' => 'upstream_credentials', 'column' => 'secret', 'json' => false],
            ['table' => 'users', 'column' => 'two_factor_secret', 'json' => false],
            ['table' => 'users', 'column' => 'two_factor_recovery_codes', 'json' => true],
        ];
    }
}
