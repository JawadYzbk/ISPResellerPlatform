<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CollectorSyncToken;
use App\Support\CollectorTerritories;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetCollectorSyncSnapshot implements Action
{
    public function __construct(
        private CollectorSyncToken $tokens,
        private CollectorTerritories $territories,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Tenant $tenant, User $user, ?string $zone, ?string $since): array
    {
        $asOf = CarbonImmutable::now();
        $sinceAt = $since === null ? null : $this->tokens->read($since, $tenant->id, $user->id);

        $customerQuery = Customer::query()
            ->withTrashed()
            ->with('zone')
            ->when($zone !== null, fn (Builder $query): Builder => $query->whereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->where('code', $zone)))
            ->when($sinceAt !== null, fn (Builder $query): Builder => $query->where('updated_at', '>=', $sinceAt))
            ->orderBy('id');
        $this->territories->constrainCustomers($customerQuery, $user);
        $customers = $customerQuery->get();

        $territoryZoneIds = $this->territories->zoneIds($user);
        $serviceQuery = Service::query()
            ->withTrashed()
            ->with(['customer', 'plan'])
            ->when($territoryZoneIds !== null, fn (Builder $query): Builder => $query->whereHas('customer', fn (Builder $customerQuery): Builder => $customerQuery->whereIn('zone_id', $territoryZoneIds)))
            ->when($zone !== null, fn (Builder $query): Builder => $query->whereHas('customer', fn (Builder $customerQuery): Builder => $customerQuery->whereHas('zone', fn (Builder $zoneQuery): Builder => $zoneQuery->where('code', $zone))))
            ->when($sinceAt !== null, fn (Builder $query): Builder => $query->where('updated_at', '>=', $sinceAt))
            ->orderBy('id');
        $services = $serviceQuery->get();
        $plans = Plan::query()
            ->when($sinceAt !== null, fn (Builder $query): Builder => $query->where('updated_at', '>=', $sinceAt))
            ->orderBy('id')
            ->get();

        return [
            'sync_token' => $this->tokens->issue($tenant->id, $user->id, $asOf),
            'generated_at' => $asOf->toIso8601String(),
            'since' => $since,
            'territory' => [
                'mode' => $territoryZoneIds === null ? 'all' : 'assigned',
                'zone_ids' => $territoryZoneIds ?? [],
            ],
            'data' => [
                'customers' => $customers->map(fn (Customer $customer): array => $this->customer($customer))->values(),
                'services' => $services->map(fn (Service $service): array => $this->service($service))->values(),
                'plans' => $plans->map(fn (Plan $plan): array => $this->plan($plan))->values(),
                'exchange_rates' => $this->rates($sinceAt),
                'message_templates' => $this->templates($sinceAt),
            ],
            'tombstones' => [
                'customers' => $customers->filter(fn (Customer $customer): bool => $customer->trashed())->map(fn (Customer $customer): string => $customer->public_id)->values(),
                'services' => $services->filter(fn (Service $service): bool => $service->trashed())->map(fn (Service $service): string => $service->public_id)->values(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function customer(Customer $customer): array
    {
        return [
            'id' => $customer->public_id,
            'code' => $customer->code,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'status' => $customer->status?->value,
            'balance_amount' => $customer->balance_amount,
            'balance_currency' => $customer->balance_currency,
            'zone' => $customer->zone === null ? null : ['code' => $customer->zone->code, 'name' => $customer->zone->name],
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'deleted_at' => $customer->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function service(Service $service): array
    {
        return [
            'id' => $service->public_id,
            'customer_id' => $service->customer?->public_id,
            'plan_id' => $service->plan?->public_id,
            'username' => $service->username,
            'status' => $service->status?->value,
            'network_state' => $service->network_state?->value,
            'expires_at' => $service->expires_at?->toIso8601String(),
            'current_period_bytes' => $service->current_period_bytes,
            'fup_applied_at' => $service->fup_applied_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
            'deleted_at' => $service->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function plan(Plan $plan): array
    {
        return [
            'id' => $plan->public_id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'download_kbps' => $plan->download_kbps,
            'upload_kbps' => $plan->upload_kbps,
            'duration_days' => $plan->duration_days,
            'amount_minor' => $plan->amount_minor,
            'currency' => $plan->currency,
            'status' => $plan->status,
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rates(?CarbonImmutable $since): array
    {
        $rates = ExchangeRate::query()
            ->when($since !== null, fn (Builder $query): Builder => $query->where('updated_at', '>=', $since))
            ->orderBy('effective_from')
            ->get();
        $result = [];
        foreach ($rates as $rate) {
            $result[] = [
                'base_currency' => $rate->base_currency,
                'quote_currency' => $rate->quote_currency,
                'rate_numerator' => $rate->rate_numerator,
                'rate_denominator' => $rate->rate_denominator,
                'effective_from' => $rate->effective_from?->toIso8601String(),
                'source' => $rate->source,
                'updated_at' => $rate->updated_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function templates(?CarbonImmutable $since): array
    {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->when($since !== null, fn (Builder $query): Builder => $query->where('updated_at', '>=', $since))
            ->orderBy('key')
            ->get()
            ->map(fn (MessageTemplate $template): array => [
                'key' => $template->key,
                'channel' => $template->channel,
                'locale' => $template->locale,
                'subject' => $template->subject,
                'body' => $template->body,
                'variables' => $template->variables,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ])->values()->all();
    }
}
