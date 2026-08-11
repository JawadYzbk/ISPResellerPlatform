<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetWhatsAppSetupStatus;
use App\Actions\GetWorkspaceSetupSignals;
use App\Actions\QueueWhatsAppTestMessage;
use App\Actions\UpdateTenantSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantSettingsRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function general(Request $request, GetWorkspaceSetupSignals $setupSignals): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $settings = $tenant->settingsData();

        return Inertia::render('Settings/General', [
            'tenant' => [
                ...$tenant->only(['public_id', 'name', 'slug']),
                'logo_url' => $tenant->logo_path === null ? null : route('tenant.logo', $tenant),
            ],
            'settings' => [
                'locale' => $settings->locale,
                'timezone' => $settings->timezone,
                'base_currency' => $settings->baseCurrency,
                'collection_currency' => $settings->collectionCurrency,
                'date_format' => $settings->dateFormat,
                'time_format' => $settings->timeFormat,
                'rtl' => $settings->rtl,
                'grace_extends_period' => (bool) ($settings->settings['grace_extends_period'] ?? false),
                'notification_quiet_start' => (string) ($settings->settings['notification_quiet_start'] ?? '22:00'),
                'notification_quiet_end' => (string) ($settings->settings['notification_quiet_end'] ?? '07:00'),
                'resolved_ticket_auto_close_hours' => (int) ($settings->settings['resolved_ticket_auto_close_hours'] ?? 72),
                'radius_interim_interval_seconds' => (int) ($settings->settings['radius_interim_interval_seconds'] ?? 300),
            ],
            'payments' => [
                'cash_enabled' => true,
                'whish_enabled' => (bool) config('services.whish.enabled', false),
                'stripe_enabled' => (string) config('services.payments.driver', 'null') === 'stripe'
                    && filled(config('services.stripe.publishable_key')),
            ],
            'setup' => $setupSignals->handle($tenant),
        ]);
    }

    public function updateGeneral(TenantSettingsRequest $request, UpdateTenantSettings $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $update->handle($tenant, $request->validated());

        return redirect()->route('settings.general')->with('success', 'Workspace settings updated.');
    }

    public function whatsapp(Request $request, GetWhatsAppSetupStatus $status): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);

        return Inertia::render('Settings/WhatsApp', ['setup' => $status->handle()]);
    }

    public function sendWhatsAppTest(Request $request, GetWhatsAppSetupStatus $status, QueueWhatsAppTestMessage $send): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $setup = $status->handle();
        $ready = $setup['mode'] === 'web'
            ? $setup['status'] === 'ready'
            : $setup['status'] === 'configured';
        if (! $ready) {
            return back()->with('error', $setup['detail'] ?? 'WhatsApp is not ready for a test message.');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]{8,32}$/'],
        ]);
        $send->handle($tenant, $user, (string) $validated['phone']);

        return redirect()->route('settings.whatsapp')->with('success', 'WhatsApp test message queued.');
    }
}
