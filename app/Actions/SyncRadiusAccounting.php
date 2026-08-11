<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\RadiusAccounting;
use App\Models\RadiusNas;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class SyncRadiusAccounting implements Action
{
    public function __construct(private UpsertCurrentSession $upsertCurrentSession) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($tenant, $at): int {
            $nasNames = RadiusNas::query()->pluck('nasname')->all();
            if ($nasNames === []) {
                return 0;
            }

            $observedAt = $at ?? CarbonImmutable::now();
            $rows = RadiusAccounting::query()
                ->where(function (Builder $query) use ($tenant, $nasNames): void {
                    $query->where('tenant_id', $tenant->id)
                        ->orWhere(function (Builder $query) use ($nasNames): void {
                            $query->whereNull('tenant_id')->whereIn('nasipaddress', $nasNames);
                        });
                })
                ->whereNotNull('username')
                ->where(function (Builder $query) use ($observedAt): void {
                    $cutoff = $observedAt->subHours(2);
                    $query->whereNull('acctstoptime')
                        ->orWhere('acctupdatetime', '>=', $cutoff)
                        ->orWhere('acctstoptime', '>=', $cutoff);
                })
                ->orderBy('radacctid')
                ->get();
            $synced = 0;
            $serviceByUsername = [];
            $interimInterval = max(1, (int) ($tenant->settingsData()->settings['radius_interim_interval_seconds'] ?? 300));

            foreach ($rows as $row) {
                $service = $serviceByUsername[$row->username] ??= Service::query()->where('username', $row->username)->first();
                if ($service === null || blank($row->acctsessionid)) {
                    continue;
                }

                DB::table('radacct')->where('radacctid', $row->radacctid)->update([
                    'tenant_id' => $tenant->id,
                    'service_id' => $service->id,
                ]);

                if ($row->acctstoptime === null) {
                    $lastSeen = $row->acctupdatetime ?? $row->acctstarttime ?? $observedAt;
                    if ($lastSeen->lt($observedAt->subSeconds($interimInterval * 2))) {
                        continue;
                    }
                    $startedAt = $row->acctstarttime === null ? null : CarbonImmutable::instance($row->acctstarttime);
                    $this->upsertCurrentSession->handle(
                        $service,
                        (string) $row->acctsessionid,
                        (string) $row->nasipaddress,
                        CarbonImmutable::instance($lastSeen),
                        max(0, (int) ($row->acctinputoctets ?? 0)),
                        max(0, (int) ($row->acctoutputoctets ?? 0)),
                        $row->framedipaddress,
                        $startedAt,
                    );
                } else {
                    CurrentSession::query()
                        ->where('service_id', $service->id)
                        ->where('acct_session_id', $row->acctsessionid)
                        ->whereNull('stopped_at')
                        ->update([
                            'stopped_at' => $row->acctstoptime,
                            'terminate_cause' => $row->acctterminatecause ?: 'Accounting-Stop',
                            'input_octets' => max(0, (int) ($row->acctinputoctets ?? 0)),
                            'output_octets' => max(0, (int) ($row->acctoutputoctets ?? 0)),
                            'updated_at' => now(),
                        ]);
                }

                $synced++;
            }

            return $synced;
        });
    }
}
