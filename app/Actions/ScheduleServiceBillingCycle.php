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

final readonly class ScheduleServiceBillingCycle implements Action
{
    public function __construct(private PreviewServiceBillingCycle $preview) {}

    public function handle(Service $service, int $anchorDay, User $actor): Service
    {
        if ((int) $actor->tenant_id !== (int) $service->tenant_id) {
            throw new DomainException('The service does not belong to the active tenant.');
        }

        return DB::transaction(function () use ($service, $anchorDay, $actor): Service {
            $locked = Service::query()->with(['plan', 'tenant'])->lockForUpdate()->findOrFail($service->id);
            if ($locked->billing_anchor_day === $anchorDay && ! is_array(($locked->metadata ?? [])['pending_billing_cycle'] ?? null)) {
                throw new DomainException('This service already uses that billing anchor day.');
            }
            if ($this->hasOpenRenewalInvoice($locked)) {
                throw new DomainException('Settle or void the open renewal invoice before changing this billing cycle.');
            }
            $quote = $this->preview->handle($locked, $anchorDay);
            $metadata = $locked->metadata ?? [];
            $applyImmediately = $locked->expires_at === null;

            if ($applyImmediately) {
                unset($metadata['pending_billing_cycle']);
                $locked->forceFill(['billing_anchor_day' => $anchorDay, 'metadata' => $metadata])->save();
            } else {
                $metadata['pending_billing_cycle'] = [
                    ...$quote->toArray(),
                    'requested_at' => now()->toIso8601String(),
                    'requested_by_id' => $actor->id,
                ];
                $locked->forceFill(['metadata' => $metadata])->save();
            }

            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => $applyImmediately ? 'billing_cycle_set' : 'billing_cycle_change_scheduled',
                'metadata' => [
                    'from_anchor_day' => $service->billing_anchor_day,
                    'to_anchor_day' => $anchorDay,
                    'apply_at' => $applyImmediately ? null : $quote->endsAt->toIso8601String(),
                    'prorated_amount' => $quote->proratedAmount,
                    'currency' => $quote->currency,
                ],
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
