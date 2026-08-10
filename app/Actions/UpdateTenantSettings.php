<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTenantSettings implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data): Tenant {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $settings = $locked->settingsData()->toArray();
            $settings = array_replace($settings, [
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'base_currency' => $data['base_currency'],
                'collection_currency' => $data['collection_currency'],
                'date_format' => $data['date_format'],
                'time_format' => $data['time_format'],
                'rtl' => $data['rtl'],
                'grace_extends_period' => $data['grace_extends_period'],
                'notification_quiet_start' => $data['notification_quiet_start'],
                'notification_quiet_end' => $data['notification_quiet_end'],
                'resolved_ticket_auto_close_hours' => $data['resolved_ticket_auto_close_hours'],
                'radius_interim_interval_seconds' => $data['radius_interim_interval_seconds'],
            ]);

            $locked->forceFill([
                'name' => $data['name'],
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'base_currency' => $data['base_currency'],
                'collection_currency' => $data['collection_currency'],
                'settings' => $settings,
            ])->save();

            return $locked->refresh();
        });
    }
}
