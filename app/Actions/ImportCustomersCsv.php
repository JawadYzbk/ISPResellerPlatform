<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ImportCustomersCsv implements Action
{
    public function __construct(private PhoneNormalizer $phones) {}

    public function handle(Tenant $tenant, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        return app(Tenancy::class)->run($tenant, function () use ($tenant, $contents, $filename, $dryRun): ImportBatch {
            $rows = $this->parse($contents);
            $batch = ImportBatch::create(['type' => 'customers', 'filename' => $filename, 'status' => $dryRun ? 'preview' : 'processing', 'total_rows' => count($rows)]);
            $report = $this->validateRows($tenant, $rows);
            $successful = count(array_filter($report, fn (array $row): bool => $row['status'] === 'valid'));
            $failed = count($report) - $successful;

            if (! $dryRun) {
                DB::transaction(function () use (&$report): void {
                    foreach ($report as &$row) {
                        if ($row['status'] !== 'valid') {
                            continue;
                        }
                        $customer = Customer::create($row['data']);
                        $row['status'] = 'imported';
                        $row['customer_id'] = $customer->id;
                    }
                    unset($row);
                });
            }

            $batch->forceFill([
                'status' => $dryRun ? 'preview' : 'completed',
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'report' => $report,
                'completed_at' => $dryRun ? null : now(),
            ])->save();

            return $batch->refresh();
        });
    }

    /** @return list<array<string, string>> */
    private function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];
        if ($lines === []) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }
        $headers = array_map(fn (string $header): string => strtolower(trim($header)), str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', array_shift($lines)) ?: ''));
        if (! in_array('first_name', $headers, true) || ! in_array('phone', $headers, true)) {
            throw new InvalidArgumentException('The CSV must include first_name and phone columns.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param list<array<string, string>> $rows @return list<array{row: int, status: string, errors: list<string>, data: array<string, mixed>, customer_id?: int}> */
    private function validateRows(Tenant $tenant, array $rows): array
    {
        $normalizedPhones = [];
        $existingPhones = [];
        $existingCodes = [];
        $codes = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            if (filled($row['phone'] ?? null)) {
                try {
                    $normalizedPhones[$rowNumber] = $this->phones->normalize($row['phone']);
                } catch (InvalidArgumentException) {
                    // The row-level report carries the useful validation error.
                }
            }
            if (filled($row['code'] ?? null)) {
                $codes[$rowNumber] = $row['code'];
            }
        }
        if ($normalizedPhones !== []) {
            $existingPhones = Customer::withTrashed()->whereIn('phone_normalized', array_values($normalizedPhones))->pluck('phone_normalized')->all();
        }
        if ($codes !== []) {
            $existingCodes = Customer::withTrashed()->whereIn('code', array_values($codes))->pluck('code')->all();
        }

        $seenPhones = [];
        $seenCodes = [];
        $report = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $phone = $normalizedPhones[$rowNumber] ?? null;
            $code = filled($row['code'] ?? null) ? $row['code'] : 'CUS-'.strtoupper(Str::random(8));
            if (blank($row['first_name'] ?? null)) {
                $errors[] = 'first_name is required';
            }
            if ($phone === null) {
                $errors[] = 'phone is missing or invalid';
            } elseif (in_array($phone, $existingPhones, true) || isset($seenPhones[$phone])) {
                $errors[] = 'phone already exists';
            }
            if (strlen($code) > 32 || in_array($code, $existingCodes, true) || isset($seenCodes[$code])) {
                $errors[] = 'code already exists or is longer than 32 characters';
            }
            if (filled($row['email'] ?? null) && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'email is invalid';
            }
            $data = [
                'code' => $code,
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? null,
                'phone' => $row['phone'] ?? '',
                'phone_normalized' => $phone ?? '',
                'email' => filled($row['email'] ?? null) ? $row['email'] : null,
                'address' => filled($row['address'] ?? null) ? $row['address'] : null,
                'status' => CustomerStatus::Active,
                'balance_currency' => $tenant->base_currency,
            ];
            if ($errors === []) {
                $seenPhones[$phone] = true;
                $seenCodes[$code] = true;
            }
            $report[] = ['row' => $rowNumber, 'status' => $errors === [] ? 'valid' : 'rejected', 'errors' => $errors, 'data' => $data];
        }

        return $report;
    }
}
