<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordPlatformAudit implements Action
{
    /** @param array<string, mixed> $properties */
    public function handle(User $actor, string $description, ?Tenant $tenant = null, array $properties = []): void
    {
        $requestId = null;
        if (app()->bound(RequestContext::class)) {
            try {
                $requestId = app(RequestContext::class)->requestId();
            } catch (LogicException) {
                $requestId = null;
            }
        }

        DB::table('activity_log')->insert([
            'tenant_id' => null,
            'ip_address' => app()->bound('request') ? request()->ip() : null,
            'request_id' => $requestId,
            'log_name' => 'platform',
            'description' => $description,
            'subject_type' => $tenant === null ? null : Tenant::class,
            'subject_id' => $tenant?->getKey(),
            'causer_type' => User::class,
            'causer_id' => $actor->getKey(),
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
