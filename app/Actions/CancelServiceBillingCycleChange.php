<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CancelServiceBillingCycleChange implements Action
{
    public function handle(Service $service, User $actor): Service
    {
        if ((int) $actor->tenant_id !== (int) $service->tenant_id) {
            throw new DomainException('The service does not belong to the active tenant.');
        }

        return DB::transaction(function () use ($service, $actor): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            $metadata = $locked->metadata ?? [];
            $pending = $metadata['pending_billing_cycle'] ?? null;
            if (! is_array($pending)) {
                throw new DomainException('This service has no scheduled billing-cycle change.');
            }
            if ($this->hasOpenRenewalInvoice($locked)) {
                throw new DomainException('Settle or void the open renewal invoice before cancelling this billing-cycle change.');
            }
            unset($metadata['pending_billing_cycle']);
            $locked->forceFill(['metadata' => $metadata])->save();
            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => 'billing_cycle_change_cancelled',
                'metadata' => ['anchor_day' => $pending['anchor_day'] ?? null],
            ]);

            return $locked->refresh();
        });
    }

    private function hasOpenRenewalInvoice(Service $service): bool
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Issued])
            ->whereHas('lines', fn ($query) => $query->where('service_id', $service->id))
            ->exists();
    }
}
