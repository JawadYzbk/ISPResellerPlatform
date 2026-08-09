<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CredentialBatch;
use App\Models\UpstreamCredential;
use Illuminate\Support\Facades\DB;

final readonly class ImportCredentials implements Action
{
    /** @param list<array{identifier: string, secret: string}> $rows */
    public function handle(CredentialBatch $batch, array $rows): int
    {
        return DB::transaction(function () use ($batch, $rows): int {
            $count = 0;
            foreach ($rows as $row) {
                UpstreamCredential::create([
                    'credential_batch_id' => $batch->id,
                    'identifier' => $row['identifier'],
                    'secret' => $row['secret'],
                    'lookup_hash' => hash('sha256', $row['identifier']),
                    'status' => 'available',
                    'expires_at' => $batch->expires_at,
                ]);
                $count++;
            }

            return $count;
        });
    }
}
