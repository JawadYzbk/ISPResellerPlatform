<?php

namespace App\Support\Api;

use App\Models\ImportBatch;

final readonly class ImportBatchApiResource
{
    /** @return array<string, mixed> */
    public function make(ImportBatch $batch): array
    {
        return [
            'id' => $batch->public_id,
            'type' => $batch->type,
            'filename' => $batch->filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'successful_rows' => $batch->successful_rows,
            'failed_rows' => $batch->failed_rows,
            'report' => collect($batch->report ?? [])
                ->map(fn (array $row): array => $this->sanitize($row, (string) $batch->type))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function sanitize(array $value, string $type): array
    {
        $sanitized = [];
        foreach ($value as $key => $item) {
            if ($this->isInternalOrSensitive($key, $type)) {
                continue;
            }
            $sanitized[$key] = is_array($item) ? $this->sanitize($item, $type) : $item;
        }

        return $sanitized;
    }

    private function isInternalOrSensitive(string $key, string $type): bool
    {
        if (in_array($key, ['password', 'password_encrypted', 'customer_id', 'plan_id', 'inventory_item_id', 'inventory_unit_id', 'journal_entry_id', 'reversal_journal_entry_id'], true)) {
            return true;
        }

        return $key === 'service_id' && $type !== 'router_subscribers';
    }
}
