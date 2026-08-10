<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CustomerSavedView;
use App\Models\User;

final readonly class SaveCustomerView implements Action
{
    /** @var list<string> */
    private const FILTERS = ['search', 'status', 'zone_id', 'expires_from', 'expires_to'];

    /** @var list<string> */
    private const COLUMNS = ['zone', 'services', 'balance', 'expiry', 'status'];

    /** @param array<string, mixed> $data */
    public function handle(User $user, array $data): CustomerSavedView
    {
        $rawFilters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $filters = [];
        foreach (self::FILTERS as $key) {
            $value = $rawFilters[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $filters[$key] = trim((string) $value);
            }
        }

        $rawColumns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
        $columns = array_values(array_unique(array_filter(
            $rawColumns,
            fn (mixed $column): bool => is_string($column) && in_array($column, self::COLUMNS, true),
        )));

        return CustomerSavedView::create([
            'user_id' => $user->id,
            'name' => trim((string) $data['name']),
            'filters' => $filters,
            'columns' => $columns,
        ]);
    }
}
