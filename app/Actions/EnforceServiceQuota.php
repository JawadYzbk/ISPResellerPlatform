<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EnforceServiceQuota implements Action
{
    public function __construct(private TransitionService $transition, private EnqueueNetworkCommand $enqueue, private QueueMessage $queueMessage) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($tenant, $at): int {
            $now = $at ?? CarbonImmutable::now();
            $enforced = 0;
            Service::query()->with(['plan', 'customer'])->where('status', ServiceStatus::Active)->chunkById(100, function ($services) use (&$enforced, $now, $tenant): void {
                foreach ($services as $service) {
                    $enforced += $this->enforce($tenant, $service, $now) ? 1 : 0;
                }
            });

            return $enforced;
        });
    }

    private function enforce(Tenant $tenant, Service $service, CarbonImmutable $at): bool
    {
        return DB::transaction(function () use ($tenant, $service, $at): bool {
            $locked = Service::query()->with(['plan', 'customer'])->lockForUpdate()->findOrFail($service->id);
            $quota = (int) ($locked->plan->metadata['quota_bytes'] ?? 0);
            if ($quota < 1) {
                return false;
            }
            $cycleStart = $locked->expires_at === null
                ? $at->startOfMonth()
                : CarbonImmutable::instance($locked->expires_at)->subDays(max(1, (int) $locked->plan->duration_days))->startOfDay();
            $used = (int) UsageDaily::query()->where('service_id', $locked->id)->whereDate('usage_date', '>=', $cycleStart->toDateString())->whereDate('usage_date', '<=', $at->toDateString())->sum('total_octets');
            $locked->forceFill(['current_period_bytes' => $used])->save();
            $this->queueWarnings($tenant, $locked, $used, $quota, $cycleStart);
            if ($used < $quota || ($locked->fup_applied_at !== null && CarbonImmutable::instance($locked->fup_applied_at)->greaterThanOrEqualTo($cycleStart))) {
                return false;
            }

            $action = (string) ($locked->plan->metadata['fup_action'] ?? 'throttle');
            $metadata = ['reason' => 'quota', 'quota_bytes' => $quota, 'used_bytes' => $used, 'fup_action' => $action];
            if ($action !== 'block') {
                $metadata['fup_profile'] = (string) ($locked->plan->metadata['fup_profile'] ?? 'fup');
            }
            if ($action === 'block') {
                $updated = $this->transition->handle($locked, ServiceStatus::Suspended, metadata: $metadata);
                $this->enqueue->handle($updated, 'suspend', $metadata);
            } else {
                $locked->forceFill(['fup_applied_at' => $at])->save();
                $this->enqueue->handle($locked->refresh(), 'throttle', $metadata);
            }

            return true;
        });
    }

    private function queueWarnings(Tenant $tenant, Service $service, int $used, int $quota, CarbonImmutable $cycleStart): void
    {
        $customer = $service->customer;
        if ($customer === null || ($customer->notification_preferences['usage_warnings'] ?? true) === false) {
            return;
        }
        $template = MessageTemplate::query()->where('key', 'service.quota_warning')->where('channel', 'sms')->where('locale', $tenant->locale ?: 'en')->where('is_active', true)->first();
        if ($template === null) {
            return;
        }
        $thresholds = $service->plan->metadata['quota_warning_thresholds'] ?? [0.8, 0.95];
        foreach ((array) $thresholds as $threshold) {
            $threshold = (float) $threshold;
            if ($threshold <= 0 || $used < (int) ceil($quota * $threshold)) {
                continue;
            }
            $percent = (int) round($threshold * 100);
            $key = 'quota-warning:'.$service->id.':'.$cycleStart->toDateString().':'.$percent;
            if (Message::query()->where('idempotency_key', $key)->exists()) {
                continue;
            }
            $this->queueMessage->handle($template, $customer->phone, 'sms', $template->locale, $key, [
                'customer_name' => $customer->full_name,
                'service_username' => $service->username,
                'quota_percent' => $percent,
                'used_bytes' => $used,
                'quota_bytes' => $quota,
            ], $customer);
        }
    }
}
