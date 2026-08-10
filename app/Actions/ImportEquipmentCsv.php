<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ImportBatch;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final readonly class ImportEquipmentCsv implements Action
{
    public function handle(Tenant $tenant, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        return app(Tenancy::class)->run($tenant, function () use ($contents, $filename, $dryRun): ImportBatch {
            $rows = $this->parse($contents);
            $batch = ImportBatch::create(['type' => 'equipment', 'filename' => $filename, 'status' => $dryRun ? 'preview' : 'processing', 'total_rows' => count($rows)]);
            $report = $this->validateRows($rows);
            $successful = count(array_filter($report, fn (array $row): bool => $row['status'] === 'valid'));
            $failed = count($report) - $successful;

            if (! $dryRun) {
                DB::transaction(function () use (&$report): void {
                    foreach ($report as &$row) {
                        if ($row['status'] !== 'valid') {
                            continue;
                        }
                        $unit = InventoryUnit::create($row['data']);
                        $row['status'] = 'imported';
                        $row['inventory_unit_id'] = $unit->id;
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
        $required = ['sku', 'warehouse_code', 'serial_number'];
        if (array_diff($required, $headers) !== []) {
            throw new InvalidArgumentException('The CSV must include sku, warehouse_code and serial_number columns.');
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

    /** @param list<array<string, string>> $rows @return list<array{row: int, status: string, errors: list<string>, data: array<string, mixed>, inventory_unit_id?: int}> */
    private function validateRows(array $rows): array
    {
        $skus = array_values(array_filter(array_map(fn (array $row): string => trim($row['sku'] ?? ''), $rows)));
        $warehouseCodes = array_values(array_filter(array_map(fn (array $row): string => trim($row['warehouse_code'] ?? ''), $rows)));
        $serials = array_values(array_filter(array_map(fn (array $row): string => trim($row['serial_number'] ?? ''), $rows)));
        $serviceUsernames = array_values(array_filter(array_map(fn (array $row): string => trim($row['service_username'] ?? ''), $rows)));
        $items = InventoryItem::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $warehouses = Warehouse::query()->whereIn('code', $warehouseCodes)->pluck('id', 'code')->map(fn (mixed $id): int => (int) $id)->all();
        $services = $serviceUsernames === [] ? [] : Service::query()->whereIn('username', $serviceUsernames)->pluck('id', 'username')->map(fn (mixed $id): int => (int) $id)->all();
        $existingSerials = InventoryUnit::query()->whereIn('serial_number', $serials)->pluck('serial_number')->all();
        $seenSerials = [];
        $report = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $sku = trim($row['sku'] ?? '');
            $warehouseCode = trim($row['warehouse_code'] ?? '');
            $serial = trim($row['serial_number'] ?? '');
            $status = strtolower(trim($row['status'] ?? 'available'));
            $serviceUsername = trim($row['service_username'] ?? '');
            $assignedAt = $this->date($row['assigned_at'] ?? null, $errors);
            $item = $items->get($sku);

            if (! $item instanceof InventoryItem) {
                $errors[] = 'sku does not exist';
            } elseif (! $item->is_serialized) {
                $errors[] = 'sku is not a serialized inventory item';
            }
            if (! isset($warehouses[$warehouseCode])) {
                $errors[] = 'warehouse_code does not exist';
            }
            if ($serial === '' || strlen($serial) > 128) {
                $errors[] = 'serial_number is required and must be at most 128 characters';
            } elseif (in_array($serial, $existingSerials, true) || isset($seenSerials[$serial])) {
                $errors[] = 'serial_number already exists';
            }
            if (! in_array($status, ['available', 'reserved', 'assigned', 'returned', 'damaged'], true)) {
                $errors[] = 'status is invalid';
            }
            if ($serviceUsername !== '' && ! isset($services[$serviceUsername])) {
                $errors[] = 'service_username does not exist';
            }
            if ($status === 'assigned' && $serviceUsername === '') {
                $errors[] = 'service_username is required for assigned equipment';
            }
            if ($status !== 'assigned' && $serviceUsername !== '') {
                $errors[] = 'service_username is only valid for assigned equipment';
            }

            $data = [
                'inventory_item_id' => $item instanceof InventoryItem ? $item->id : 0,
                'warehouse_id' => $warehouses[$warehouseCode] ?? 0,
                'serial_number' => $serial,
                'status' => $status,
                'service_id' => $services[$serviceUsername] ?? null,
                'assigned_at' => $assignedAt,
            ];
            if ($errors === []) {
                $seenSerials[$serial] = true;
            }
            $report[] = ['row' => $rowNumber, 'status' => $errors === [] ? 'valid' : 'rejected', 'errors' => $errors, 'data' => $data];
        }

        return $report;
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
            $errors[] = 'assigned_at is invalid';

            return null;
        }
    }
}
