<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Incident;
use App\Models\InventoryItem;
use App\Models\NetworkCommand;
use App\Models\Router;
use App\Models\Service;
use App\Models\WorkOrder;
use App\Support\StockQuantity;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetOperationsReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'service_counts_by_status' => $this->counts(Service::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'expiring_services' => Service::query()->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'work_order_counts_by_status' => $this->counts(WorkOrder::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'incident_counts_by_status' => $this->counts(Incident::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'active_sessions' => CurrentSession::query()->whereNull('stopped_at')->count(),
            'offline_routers' => Router::query()->where('status', 'offline')->count(),
            'network_drift' => Service::query()->whereIn('network_state', ['drifted', 'failed'])->count(),
            'failed_commands' => NetworkCommand::query()->where('status', 'failed')->count(),
            'low_stock_items' => InventoryItem::query()
                ->where('is_active', true)
                ->where('reorder_level', '>', 0)
                ->withCount(['units as available_units' => fn (Builder $query): Builder => $query->where('status', 'available')])
                ->withSum('stockBalances as bulk_units', 'quantity')
                ->get()
                ->map(function (InventoryItem $item): array {
                    $available = $item->is_serialized ? (string) $item->available_units : StockQuantity::format((string) ($item->bulk_units ?? '0.000'));

                    return [
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'available_units' => $item->is_serialized ? (int) $item->available_units : $available,
                        'reorder_level' => $item->reorder_level,
                        '_available_for_sort' => $available,
                    ];
                })
                ->filter(fn (array $item): bool => ! StockQuantity::greaterThan($item['_available_for_sort'], (string) $item['reorder_level']))
                ->sortBy('_available_for_sort')
                ->take(10)
                ->map(fn (array $item): array => ['sku' => $item['sku'], 'name' => $item['name'], 'available_units' => $item['available_units'], 'reorder_level' => $item['reorder_level']])
                ->values()
                ->all(),
        ];
    }

    /** @param array<mixed, mixed> $values @return array<string, int> */
    private function counts(array $values): array
    {
        $counts = [];
        foreach ($values as $key => $value) {
            $counts[(string) $key] = (int) $value;
        }

        return $counts;
    }
}
