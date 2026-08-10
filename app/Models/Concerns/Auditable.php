<?php

namespace App\Models\Concerns;

use App\Models\AuditEvent;
use App\Support\RequestContext;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(AuditEvent $activity, string $eventName): void
    {
        $activity->tenant_id = app(Tenancy::class)->id();
        $activity->ip_address = app()->bound('request') ? request()->ip() : null;

        if (app()->bound(RequestContext::class)) {
            try {
                $activity->request_id = app(RequestContext::class)->requestId();
            } catch (LogicException) {
                $activity->request_id = null;
            }
        }

        if (Auth::check()) {
            $activity->causer_id ??= Auth::id();
        }
    }
}
