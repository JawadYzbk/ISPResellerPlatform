<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ImportPlansCsv implements Action
{
    public function handle(Tenant $tenant, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        return app(Tenancy::class)->run($tenant, function () use ($contents, $filename, $dryRun): ImportBatch {
            $rows = $this->parse($contents);
            $batch = ImportBatch::create(['type' => 'plans', 'filename' => $filename, 'status' => $dryRun ? 'preview' : 'processing', 'total_rows' => count($rows)]);
            $report = $this->validateRows($rows);
            $successful = count(array_filter($report, fn (array $row): bool => $row['status'] === 'valid'));
            $failed = count($report) - $successful;

            if (! $dryRun) {
                DB::transaction(function () use (&$report): void {
                    foreach ($report as &$row) {
                        if ($row['status'] !== 'valid') {
                            continue;
                        }
                        $plan = Plan::create($row['data']);
                        $row['status'] = 'imported';
                        $row['plan_id'] = $plan->id;
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
        $required = ['name', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency'];
        if (array_diff($required, $headers) !== []) {
            throw new InvalidArgumentException('The CSV must include name, download_kbps, upload_kbps, duration_days, amount_minor and currency columns.');
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

    /** @param list<array<string, string>> $rows @return list<array{row: int, status: string, errors: list<string>, data: array<string, mixed>, plan_id?: int}> */
    private function validateRows(array $rows): array
    {
        $slugs = [];
        foreach ($rows as $index => $row) {
            $slugs[$index + 2] = filled($row['slug'] ?? null) ? Str::slug($row['slug']) : Str::slug($row['name'] ?? '');
        }

        $existingSlugs = Plan::query()->whereIn('slug', array_values($slugs))->pluck('slug')->all();
        $seenSlugs = [];
        $report = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $slug = $slugs[$rowNumber];
            $name = trim($row['name'] ?? '');
            $download = $this->integer($row['download_kbps'] ?? null);
            $upload = $this->integer($row['upload_kbps'] ?? null);
            $duration = $this->integer($row['duration_days'] ?? null);
            $amount = $this->integer($row['amount_minor'] ?? null);
            $currency = strtoupper(trim($row['currency'] ?? ''));
            $status = strtolower(trim($row['status'] ?? 'active'));

            if ($name === '') {
                $errors[] = 'name is required';
            }
            if ($slug === '' || strlen($slug) > 255) {
                $errors[] = 'slug is missing or invalid';
            } elseif (in_array($slug, $existingSlugs, true) || isset($seenSlugs[$slug])) {
                $errors[] = 'slug already exists';
            }
            if ($download === null || $download < 0) {
                $errors[] = 'download_kbps must be a non-negative integer';
            }
            if ($upload === null || $upload < 0) {
                $errors[] = 'upload_kbps must be a non-negative integer';
            }
            if ($duration === null || $duration < 1) {
                $errors[] = 'duration_days must be a positive integer';
            }
            if ($amount === null || $amount < 0) {
                $errors[] = 'amount_minor must be a non-negative integer';
            }
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $errors[] = 'currency must be a three-letter code';
            }
            if (! in_array($status, ['active', 'inactive'], true)) {
                $errors[] = 'status must be active or inactive';
            }

            $data = [
                'name' => $name,
                'slug' => $slug,
                'download_kbps' => $download ?? 0,
                'upload_kbps' => $upload ?? 0,
                'duration_days' => $duration ?? 0,
                'amount_minor' => $amount ?? 0,
                'currency' => $currency,
                'status' => $status,
            ];
            if ($errors === []) {
                $seenSlugs[$slug] = true;
            }
            $report[] = ['row' => $rowNumber, 'status' => $errors === [] ? 'valid' : 'rejected', 'errors' => $errors, 'data' => $data];
        }

        return $report;
    }

    private function integer(?string $value): ?int
    {
        return $value !== null && preg_match('/^\d+$/', trim($value)) === 1 ? (int) $value : null;
    }
}
