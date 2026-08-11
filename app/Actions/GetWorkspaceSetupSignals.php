<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class GetWorkspaceSetupSignals implements Action
{
    public function __construct(
        private GetWhatsAppSetupStatus $whatsapp,
        private Tenancy $tenancy,
    ) {}

    /** @return array{logo_ready: bool, currency: array{base: string, collection: string, rate_ready: bool}, whatsapp: array{mode: string, configured: bool, status: string}} */
    public function handle(Tenant $tenant): array
    {
        return $this->tenancy->run($tenant, function () use ($tenant): array {
            $base = strtoupper((string) $tenant->base_currency);
            $collection = strtoupper((string) $tenant->collection_currency);
            $now = now($tenant->timezone);
            $rate = ExchangeRate::query()
                ->where('effective_from', '<=', $now)
                ->where(function ($query) use ($base, $collection): void {
                    $query->where(function ($query) use ($base, $collection): void {
                        $query->where('base_currency', $base)->where('quote_currency', $collection);
                    })->orWhere(function ($query) use ($base, $collection): void {
                        $query->where('base_currency', $collection)->where('quote_currency', $base);
                    });
                })
                ->orderByDesc('effective_from')
                ->first();
            $rateReady = $base === $collection || ($rate instanceof ExchangeRate && $this->isFresh($rate, $now));
            $whatsapp = $this->whatsapp->handle(false);

            return [
                'logo_ready' => $this->logoReady($tenant),
                'currency' => [
                    'base' => $base,
                    'collection' => $collection,
                    'rate_ready' => $rateReady,
                ],
                'whatsapp' => [
                    'mode' => $whatsapp['mode'],
                    'configured' => $whatsapp['configured'],
                    'status' => $whatsapp['status'],
                ],
            ];
        });
    }

    private function logoReady(Tenant $tenant): bool
    {
        $path = is_string($tenant->logo_path) ? trim($tenant->logo_path) : '';
        if ($path === '') {
            return false;
        }

        try {
            return Storage::disk((string) config('filesystems.default', 'local'))->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function isFresh(ExchangeRate $rate, CarbonInterface $now): bool
    {
        if (! $rate->effective_from instanceof CarbonInterface) {
            return false;
        }

        return (int) $rate->effective_from->diffInHours($now) <= max(1, (int) config('services.fx.rate_max_age_hours', 72));
    }
}
