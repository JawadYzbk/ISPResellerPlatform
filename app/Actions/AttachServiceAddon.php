<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Addon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AttachServiceAddon implements Action
{
    public function handle(Service $service, Addon $addon, int $quantity, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt, User $actor): ServiceAddon
    {
        if ((int) $service->tenant_id !== (int) $addon->tenant_id || (int) $service->tenant_id !== (int) $actor->tenant_id) {
            throw new DomainException('The service, add-on, and operator must belong to the same tenant.');
        }
        if ($addon->status !== 'active') {
            throw new DomainException('Only active add-ons can be attached to a service.');
        }
        if ($service->status->value === 'terminated') {
            throw new DomainException('Terminated services cannot receive recurring add-ons.');
        }
        if ($quantity < 1 || $quantity > 1000) {
            throw new DomainException('Add-on quantity must be between one and one thousand.');
        }
        if ($endsAt !== null && $endsAt->lessThan($startsAt)) {
            throw new DomainException('The add-on end date must be on or after its start date.');
        }

        return DB::transaction(fn (): ServiceAddon => ServiceAddon::create([
            'service_id' => $service->id,
            'addon_id' => $addon->id,
            'quantity' => $quantity,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt?->toDateString(),
            'status' => 'active',
            'metadata' => ['attached_by_id' => $actor->id],
        ])->load('addon'));
    }
}
