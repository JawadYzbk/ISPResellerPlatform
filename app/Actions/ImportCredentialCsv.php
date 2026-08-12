<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CredentialBatch;
use App\Models\Supplier;
use App\Models\UpstreamCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ImportCredentialCsv implements Action
{
    public function __construct(private ImportCredentials $importCredentials) {}

    public function handle(Supplier $supplier, string $reference, ?string $expiresAt, string $contents, array $commercial = []): CredentialBatch
    {
        $rows = $this->parse($contents);
        $lookupHashes = array_map(fn (array $row): string => hash('sha256', $row['identifier']), $rows);
        $existing = UpstreamCredential::query()->whereIn('lookup_hash', $lookupHashes)->exists();
        if ($existing) {
            throw new InvalidArgumentException('One or more credential identifiers already exist in this tenant.');
        }

        return DB::transaction(function () use ($supplier, $reference, $expiresAt, $rows, $commercial): CredentialBatch {
            $batch = CredentialBatch::create([
                'supplier_id' => $supplier->id,
                'supplier_contract_id' => $commercial['supplier_contract_id'] ?? null,
                'reference' => $reference,
                'contract_reference' => $commercial['contract_reference'] ?? null,
                'unit_cost_amount' => $commercial['unit_cost_amount'] ?? null,
                'total_cost_amount' => $commercial['total_cost_amount'] ?? null,
                'currency' => $commercial['currency'] ?? null,
                'imported_at' => now(),
                'expires_at' => $expiresAt === null ? null : CarbonImmutable::parse($expiresAt),
            ]);
            $this->importCredentials->handle($batch, $rows);

            return $batch->refresh();
        });
    }

    /** @return list<array{identifier: string, secret: string}> */
    private function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];
        if ($lines === []) {
            throw new InvalidArgumentException('The credential CSV is empty.');
        }
        $headers = array_map(fn (string $header): string => strtolower(trim($header)), str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', array_shift($lines)) ?: ''));
        if (array_diff(['identifier', 'secret'], $headers) !== []) {
            throw new InvalidArgumentException('The credential CSV must include identifier and secret columns.');
        }

        $rows = [];
        $identifiers = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
            $identifier = $row['identifier'] ?? '';
            $secret = $row['secret'] ?? '';
            if ($identifier === '' || $secret === '') {
                throw new InvalidArgumentException('Every credential row requires an identifier and a secret.');
            }
            if (isset($identifiers[$identifier])) {
                throw new InvalidArgumentException('The credential CSV contains a duplicate identifier.');
            }
            $identifiers[$identifier] = true;
            $rows[] = ['identifier' => $identifier, 'secret' => $secret];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The credential CSV contains no rows.');
        }

        return $rows;
    }
}
