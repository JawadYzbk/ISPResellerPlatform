<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class SuspendOverdueServices implements Action
{
    public function __construct(private TransitionService $transition, private EnqueueNetworkCommand $enqueue) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($at): int {
            $at ??= CarbonImmutable::now();
            $suspended = 0;
            Service::query()
                ->where('status', ServiceStatus::Active)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $at)
                ->select('id')
                ->chunkById(100, function ($services) use (&$suspended, $at): void {
                    foreach ($services as $service) {
                        DB::transaction(function () use ($service, $at, &$suspended): void {
                            $locked = Service::query()->lockForUpdate()->find($service->id);
                            if ($locked === null || $locked->status !== ServiceStatus::Active || $locked->expires_at === null || $locked->expires_at->isAfter($at)) {
                                return;
                            }

                            $updated = $this->transition->handle($locked, ServiceStatus::Suspended, metadata: [
                                'reason' => 'auto_overdue',
                                'expired_at' => $locked->expires_at->toIso8601String(),
                            ]);
                            $this->enqueue->handle($updated, 'suspend', ['reason' => 'auto_overdue']);
                            $suspended++;
                        });
                    }
                });

            return $suspended;
        });
    }
}
