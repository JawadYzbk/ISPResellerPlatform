<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            $oldLogoPath = $locked->logo_path;
            $logoPath = $oldLogoPath;
            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $logoPath = $data['logo']->store('tenants/'.$locked->public_id, 'public');
            }

            $locked->forceFill([
                'name' => $data['name'],
                'logo_path' => $logoPath,
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'base_currency' => $data['base_currency'],
                'collection_currency' => $data['collection_currency'],
                'settings' => $settings,
            ])->save();

            if (is_string($oldLogoPath) && $oldLogoPath !== '' && $oldLogoPath !== $logoPath) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return $locked->refresh();
        });
    }
}
