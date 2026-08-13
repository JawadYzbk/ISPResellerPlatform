<?php

namespace App\Http\Controllers\Web;

use App\Actions\CheckProviderConnectivity;
use App\Actions\CreateWhatsAppAccount;
use App\Actions\DeleteWhatsAppAccount;
use App\Actions\DisconnectWhatsAppAccount;
use App\Actions\GetCurrencyCatalog;
use App\Actions\GetPaymentSetupStatus;
use App\Actions\GetTenantReadiness;
use App\Actions\GetWhatsAppSetupStatus;
use App\Actions\GetWorkspaceSetupSignals;
use App\Actions\QueueWhatsAppTestMessage;
use App\Actions\UpdateTenantIntegrationSettings;
use App\Actions\UpdateTenantSettings;
use App\Actions\UpdateWhatsAppAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantIntegrationSettingsRequest;
use App\Http\Requests\TenantSettingsRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy;
use App\Support\TenantIntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function general(
        Request $request,
        GetPaymentSetupStatus $paymentStatus,
        GetWorkspaceSetupSignals $setupSignals,
        GetCurrencyCatalog $currencyCatalog,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $settings = $tenant->settingsData();

        return Inertia::render('Settings/General', [
            'tenant' => [
                ...$tenant->only(['public_id', 'name', 'slug']),
                'logo_url' => $tenant->logoUrl(),
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
            'currencies' => $currencyCatalog->handle(),
            'payments' => $paymentStatus->handle(),
            'setup' => $setupSignals->handle($tenant),
        ]);
    }

    public function readiness(Request $request, GetTenantReadiness $readiness): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $checks = $readiness->handle($tenant);
        $hasFailures = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'FAIL');
        $hasWarnings = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'WARN');

        return Inertia::render('Settings/Readiness', [
            'overall' => $hasFailures ? 'FAIL' : ($hasWarnings ? 'WARN' : 'PASS'),
            'checks' => collect($checks)->map(fn (array $check, string $name): array => [
                'name' => $name,
                ...$check,
            ])->values()->all(),
            'providerChecks' => $request->session()->get('provider_checks'),
        ]);
    }

    public function setup(Request $request, GetTenantReadiness $readiness): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $checks = $readiness->handle($tenant);

        return Inertia::render('Settings/Setup', [
            'checks' => collect($checks)->map(fn (array $check, string $name): array => [
                'name' => $name,
                ...$check,
            ])->values()->all(),
        ]);
    }

    public function integrations(Request $request, TenantIntegrationSettings $settings): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $resolved = $settings->resolved($tenant);
        $stored = $settings->stored($tenant);
        $configured = static fn (mixed $value): bool => is_string($value) ? trim($value) !== '' : $value !== null;
        $secretPaths = [
            'whatsapp_cloud_token' => 'whatsapp.token',
            'whatsapp_phone_number_id' => 'whatsapp.phone_number_id',
            'whatsapp_web_token' => 'whatsapp.web.token',
            'whatsapp_webhook_secret' => 'webhooks.secrets.whatsapp_web',
            'whatsapp_cloud_webhook_secret' => 'webhooks.secrets.whatsapp',
            'stripe_secret' => 'stripe.secret',
            'stripe_publishable_key' => 'stripe.publishable_key',
            'stripe_webhook_secret' => 'stripe.webhook_secret',
            'whish_channel' => 'whish.channel',
            'whish_secret' => 'whish.secret',
        ];

        return Inertia::render('Settings/Integrations', [
            'settings' => [
                'payment_driver' => (string) ($resolved['payments.driver'] ?? 'null'),
                'frankfurter_enabled' => (bool) ($resolved['frankfurter.enabled'] ?? false),
                'frankfurter_currency_catalog_enabled' => (bool) ($resolved['frankfurter.currency_catalog_enabled'] ?? true),
                'frankfurter_endpoint' => (string) ($resolved['frankfurter.endpoint'] ?? ''),
                'frankfurter_connect_timeout' => (int) ($resolved['frankfurter.connect_timeout'] ?? 2),
                'frankfurter_timeout' => (int) ($resolved['frankfurter.timeout'] ?? 10),
                'frankfurter_quotes' => implode(',', (array) ($resolved['frankfurter.quotes'] ?? [])),
                'whatsapp_mode' => (string) ($resolved['whatsapp.mode'] ?? 'cloud'),
                'whatsapp_web_enabled' => (bool) ($resolved['whatsapp.web.enabled'] ?? false),
                'whatsapp_web_endpoint' => (string) ($resolved['whatsapp.web.endpoint'] ?? ''),
                'whatsapp_web_client_id' => (string) ($resolved['whatsapp.web.client_id'] ?? ''),
                'whatsapp_web_webhook_url' => (string) ($resolved['whatsapp.web.webhook_url'] ?? ''),
                'stripe_endpoint' => (string) ($resolved['stripe.endpoint'] ?? ''),
                'stripe_webhook_tolerance' => (int) ($resolved['stripe.webhook_tolerance'] ?? 300),
                'stripe_timeout' => (int) ($resolved['stripe.timeout'] ?? 15),
                'whish_enabled' => (bool) ($resolved['whish.enabled'] ?? false),
                'whish_environment' => (string) ($resolved['whish.environment'] ?? 'sandbox'),
                'whish_website_url' => (string) ($resolved['whish.website_url'] ?? ''),
                'whish_endpoint' => (string) ($resolved['whish.endpoint'] ?? ''),
                'whish_timeout' => (int) ($resolved['whish.timeout'] ?? 15),
                'whish_success_callback_url' => (string) ($resolved['whish.success_callback_url'] ?? ''),
                'whish_failure_callback_url' => (string) ($resolved['whish.failure_callback_url'] ?? ''),
                'whish_success_redirect_url' => (string) ($resolved['whish.success_redirect_url'] ?? ''),
                'whish_failure_redirect_url' => (string) ($resolved['whish.failure_redirect_url'] ?? ''),
            ],
            'configured' => collect($secretPaths)->mapWithKeys(
                fn (string $path, string $field): array => [$field => $configured($resolved[$path] ?? null)],
            )->all(),
            'sources' => collect($secretPaths)->mapWithKeys(
                fn (string $path, string $field): array => [$field => array_key_exists($path, $stored) ? 'workspace' : ($configured($resolved[$path] ?? null) ? 'environment' : 'missing')],
            )->all(),
        ]);
    }

    public function updateIntegrations(
        TenantIntegrationSettingsRequest $request,
        UpdateTenantIntegrationSettings $update,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $updated = $update->handle($tenant, $request->validated());
        app(Tenancy::class)->set($updated);

        return redirect()->route('settings.integrations')
            ->with('success_title', 'Integration settings saved.')
            ->with('success', 'Provider configuration updated for this workspace.');
    }

    public function providerCheck(Request $request, CheckProviderConnectivity $check): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);

        $results = $check->handle();
        $hasIssues = collect($results)->contains(fn (array $result): bool => in_array($result['status'], ['failed', 'not_configured'], true));

        return to_route('settings.readiness')
            ->with('provider_checks', $results)
            ->with('success_title', 'Provider checks')
            ->with('success', $hasIssues
                ? 'Provider checks completed with actions required.'
                : 'All configured provider checks passed.');
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

        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        return Inertia::render('Settings/WhatsApp', ['setup' => $status->handle(tenant: $tenant)]);
    }

    public function createWhatsAppAccount(Request $request, CreateWhatsAppAccount $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'job' => ['required', Rule::in(WhatsAppAccount::JOBS)],
        ]);

        $create->handle($tenant, (string) $validated['label'], (string) $validated['job']);

        return redirect()->route('settings.whatsapp')->with('success', 'WhatsApp account added.');
    }

    public function updateWhatsAppAccount(Request $request, WhatsAppAccount $whatsappAccount, UpdateWhatsAppAccount $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        abort_unless($whatsappAccount->tenant_id === $user->tenant_id, 404);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'job' => ['required', Rule::in(WhatsAppAccount::JOBS)],
        ]);

        $update->handle($whatsappAccount, (string) $validated['label'], (string) $validated['job']);

        return redirect()->route('settings.whatsapp')->with('success', 'WhatsApp account updated.');
    }

    public function disconnectWhatsAppAccount(Request $request, WhatsAppAccount $whatsappAccount, DisconnectWhatsAppAccount $disconnect): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        abort_unless($whatsappAccount->tenant_id === $user->tenant_id, 404);
        $disconnect->handle($whatsappAccount);

        return redirect()->route('settings.whatsapp')->with('success', 'WhatsApp account disconnected and returned to pairing.');
    }

    public function deleteWhatsAppAccount(Request $request, WhatsAppAccount $whatsappAccount, DeleteWhatsAppAccount $delete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        abort_unless($whatsappAccount->tenant_id === $user->tenant_id, 404);

        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $result = $delete->handle($whatsappAccount);
        $message = 'WhatsApp account deleted.';
        if ($result['cleanup_queued']) {
            $message .= ' Bridge cleanup queued until the bridge is healthy.';
        }

        return redirect()->route('settings.whatsapp')->with('success', $message);
    }

    public function sendWhatsAppTest(Request $request, GetWhatsAppSetupStatus $status, QueueWhatsAppTestMessage $send): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);

        $setup = $status->handle(tenant: $tenant);
        $accountId = $request->string('account_id')->trim()->toString();
        if ($accountId === '') {
            $account = null;
        } else {
            $account = $tenant->whatsappAccounts()->where('public_id', $accountId)->first();
            if (! $account instanceof WhatsAppAccount) {
                throw ValidationException::withMessages(['account_id' => 'Choose a WhatsApp account from this workspace.']);
            }
        }
        $ready = $account instanceof WhatsAppAccount
            ? collect($setup['accounts'])->contains(fn (array $item): bool => $item['id'] === $account->public_id && $item['status'] === 'ready')
            : ($setup['mode'] === 'web' ? $setup['status'] === 'ready' : $setup['status'] === 'configured');
        if (! $ready) {
            return back()->with('error', $setup['detail'] ?? 'WhatsApp is not ready for a test message.');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]{8,32}$/'],
        ]);
        $send->handle($tenant, $user, (string) $validated['phone'], $account);

        return redirect()->route('settings.whatsapp')->with('success', 'WhatsApp test message queued.');
    }
}
