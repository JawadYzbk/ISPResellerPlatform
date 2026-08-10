<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final readonly class ImportBalancesCsv implements Action
{
    public function __construct(private PostJournalEntry $journal) {}

    public function handle(Tenant $tenant, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        return app(Tenancy::class)->run($tenant, function () use ($contents, $filename, $dryRun): ImportBatch {
            $rows = $this->parse($contents);
            $batch = ImportBatch::create(['type' => 'balances', 'filename' => $filename, 'status' => $dryRun ? 'preview' : 'processing', 'total_rows' => count($rows)]);
            $report = $this->validateRows($rows);
            $successful = count(array_filter($report, fn (array $row): bool => $row['status'] === 'valid'));
            $failed = count($report) - $successful;

            if (! $dryRun) {
                $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
                $openingEquity = LedgerAccount::query()->where('code', '3990')->firstOrFail();
                DB::transaction(function () use (&$report, $batch, $receivable, $openingEquity): void {
                    foreach ($report as &$row) {
                        if ($row['status'] !== 'valid') {
                            continue;
                        }
                        $amount = (int) $row['data']['amount_minor'];
                        $absolute = abs($amount);
                        $entry = $this->journal->post(
                            'Opening balance import '.($row['data']['customer_code']),
                            $amount > 0
                                ? [new JournalLineInput($receivable->id, $row['data']['currency'], debitAmount: $absolute, customerId: $row['data']['customer_id'], memo: $row['data']['memo']), new JournalLineInput($openingEquity->id, $row['data']['currency'], creditAmount: $absolute, memo: $row['data']['memo'])]
                                : [new JournalLineInput($receivable->id, $row['data']['currency'], creditAmount: $absolute, customerId: $row['data']['customer_id'], memo: $row['data']['memo']), new JournalLineInput($openingEquity->id, $row['data']['currency'], debitAmount: $absolute, memo: $row['data']['memo'])],
                            $row['data']['effective_at'],
                            sourceType: ImportBatch::class,
                            sourceId: $batch->id.':'.$row['row'],
                        );
                        $row['status'] = 'imported';
                        $row['journal_entry_id'] = $entry->id;
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
        $required = ['customer_code', 'amount_minor', 'currency'];
        if (array_diff($required, $headers) !== []) {
            throw new InvalidArgumentException('The CSV must include customer_code, amount_minor and currency columns.');
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

    /** @param list<array<string, string>> $rows @return list<array{row: int, status: string, errors: list<string>, data: array<string, mixed>, journal_entry_id?: int}> */
    private function validateRows(array $rows): array
    {
        $codes = array_values(array_filter(array_map(fn (array $row): string => trim($row['customer_code'] ?? ''), $rows)));
        $customers = Customer::query()->whereIn('code', $codes)->get()->keyBy('code');
        $historyIds = $customers->isEmpty() ? [] : DB::table('ledger_entries')->whereIn('customer_id', $customers->pluck('id'))->pluck('customer_id')->all();
        $seenCodes = [];
        $report = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $code = trim($row['customer_code'] ?? '');
            $amount = $this->amount($row['amount_minor'] ?? null);
            $currency = strtoupper(trim($row['currency'] ?? ''));
            $customer = $customers->get($code);
            $effectiveAt = $this->date($row['effective_at'] ?? null, $errors);
            $memo = trim($row['memo'] ?? 'Opening balance import');

            if (! $customer instanceof Customer) {
                $errors[] = 'customer_code does not exist';
            } elseif (in_array($customer->id, $historyIds, true) || $customer->balance_amount !== 0) {
                $errors[] = 'customer already has ledger history';
            }
            if (isset($seenCodes[$code])) {
                $errors[] = 'customer_code appears more than once';
            }
            if ($amount === null || $amount === 0) {
                $errors[] = 'amount_minor must be a non-zero integer';
            }
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $errors[] = 'currency must be a three-letter code';
            } elseif ($customer instanceof Customer && $customer->balance_currency !== $currency) {
                $errors[] = 'currency does not match customer balance currency';
            }

            $data = ['customer_code' => $code, 'customer_id' => $customer instanceof Customer ? $customer->id : 0, 'amount_minor' => $amount ?? 0, 'currency' => $currency, 'effective_at' => $effectiveAt ?? CarbonImmutable::now(), 'memo' => Str::limit($memo, 255, '')];
            if ($errors === []) {
                $seenCodes[$code] = true;
            }
            $report[] = ['row' => $rowNumber, 'status' => $errors === [] ? 'valid' : 'rejected', 'errors' => $errors, 'data' => $data];
        }

        return $report;
    }

    private function amount(?string $value): ?int
    {
        return $value !== null && preg_match('/^-?\d+$/', trim($value)) === 1 ? (int) $value : null;
    }

    /** @param list<string> $errors */
    private function date(?string $value, array &$errors): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            $errors[] = 'effective_at is invalid';

            return null;
        }
    }
}
