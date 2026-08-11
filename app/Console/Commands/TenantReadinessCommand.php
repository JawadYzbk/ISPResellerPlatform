<?php

namespace App\Console\Commands;

use App\Actions\GetWhatsAppSetupStatus;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\MessageTemplateProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TenantReadinessCommand extends Command
{
    protected $signature = 'platform:tenant-readiness
        {tenant : Tenant slug or public ID}
        {--strict : Treat warnings as failures}
        {--json : Render machine-readable JSON}';

    protected $description = 'Check whether a tenant is ready for a supervised pilot handoff.';

    public function handle(
        Tenancy $tenancy,
        GetWhatsAppSetupStatus $whatsappStatus,
        MessageTemplateProvisioner $templateProvisioner,
    ): int {
        $identifier = (string) $this->argument('tenant');
        $tenant = Tenant::query()
            ->where('slug', $identifier)
            ->orWhere('public_id', $identifier)
            ->first();

        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found: '.$identifier);

            return self::FAILURE;
        }

        /** @var array<string, array{status: 'PASS'|'WARN'|'FAIL', detail: string}> $checks */
        $checks = $tenancy->run($tenant, fn (): array => $this->checksFor($tenant, $whatsappStatus, $templateProvisioner));
        $hasFailures = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'FAIL');
        $hasWarnings = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'WARN');
        $strict = (bool) $this->option('strict');
        $status = $hasFailures || ($strict && $hasWarnings)
            ? 'FAIL'
            : ($hasWarnings ? 'WARN' : 'PASS');

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'tenant' => [
                    'slug' => $tenant->slug,
                    'public_id' => $tenant->public_id,
                ],
                'status' => $status,
                'strict' => $strict,
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Check', 'Result', 'Detail'],
                collect($checks)->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    $check['detail'],
                ])->all(),
            );

            match ($status) {
                'PASS' => $this->info('Tenant readiness passed.'),
                'WARN' => $this->warn('Tenant readiness passed with warnings.'),
                default => $this->error('Tenant readiness failed.'),
            };
        }

        return $status === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, array{status: 'PASS'|'WARN'|'FAIL', detail: string}> */
    private function checksFor(
        Tenant $tenant,
        GetWhatsAppSetupStatus $whatsappStatus,
        MessageTemplateProvisioner $templateProvisioner,
    ): array {
        $baseCurrency = strtoupper((string) $tenant->base_currency);
        $collectionCurrency = strtoupper((string) $tenant->collection_currency);
        $now = now($tenant->timezone);

        $owner = User::query()->whereIn('role', ['tenant_owner', 'admin'])->first();
        $ownerReady = $owner instanceof User
            && $owner->hasRole((string) $owner->role)
            && $owner->can('settings.manage')
            && $owner->can('customers.view');
        $hasActivePlanPrice = Plan::query()
            ->where('status', 'active')
            ->whereHas('prices', function ($query) use ($baseCurrency, $now): void {
                $query->where('currency', $baseCurrency)
                    ->where('effective_from', '<=', $now)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
                    });
            })
            ->exists();

        return [
            'Tenant status' => $this->check(
                $tenant->status === 'active' ? 'PASS' : 'FAIL',
                $tenant->status === 'active' ? 'Tenant is active.' : 'Tenant status is '.$tenant->status.'.',
            ),
            'Owner capability' => $this->check(
                $ownerReady ? 'PASS' : 'FAIL',
                $ownerReady
                    ? 'An owner account has its role assignment and critical settings/customer capabilities.'
                    : ($owner instanceof User
                        ? 'Owner account needs its capability role and critical settings/customer permissions.'
                        : 'No tenant owner or admin account exists.'),
            ),
            'Default branch' => $this->check(
                Branch::query()->where('is_default', true)->exists() ? 'PASS' : 'FAIL',
                Branch::query()->where('is_default', true)->exists() ? 'A default branch is configured.' : 'Create a default branch before onboarding staff.',
            ),
            'Service zone' => $this->check(
                Zone::query()->exists() ? 'PASS' : 'FAIL',
                Zone::query()->exists() ? 'At least one service zone is configured.' : 'Create at least one service zone before importing customers.',
            ),
            'Base currency' => $this->check(
                Currency::query()->where('code', $baseCurrency)->where('is_base', true)->where('is_active', true)->exists() ? 'PASS' : 'FAIL',
                $baseCurrency.' is provisioned as the active base currency.',
            ),
            'Collection currency' => $this->check(
                Currency::query()->where('code', $collectionCurrency)->where('is_collection', true)->where('is_active', true)->exists() ? 'PASS' : 'FAIL',
                $collectionCurrency.' is provisioned as the active collection currency.',
            ),
            'Collection FX rate' => $this->collectionFxCheck($baseCurrency, $collectionCurrency, $now),
            'Billable plan' => $this->check(
                $hasActivePlanPrice ? 'PASS' : 'FAIL',
                $hasActivePlanPrice ? 'An active plan has an effective '.$baseCurrency.' price.' : 'Create an active plan with an effective '.$baseCurrency.' price.',
            ),
            'Notification templates' => $this->notificationTemplateCheck($tenant, $templateProvisioner),
            'Tenant logo' => $this->tenantLogoCheck($tenant),
            'Cash collection' => $this->check('PASS', 'Cash collection is available to authorized staff.'),
            'Stripe gateway' => $this->stripeCheck(),
            'Whish Pay gateway' => $this->whishCheck(),
            'WhatsApp channel' => $this->whatsappCheck($whatsappStatus),
        ];
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function notificationTemplateCheck(Tenant $tenant, MessageTemplateProvisioner $templateProvisioner): array
    {
        $locale = $templateProvisioner->resolveLocale($tenant);
        $expected = [];

        foreach ($templateProvisioner->expectedTemplateKeys() as $key) {
            foreach ($templateProvisioner->supportedChannels() as $channel) {
                $expected[] = $key.'::'.$channel;
            }
        }

        $actual = MessageTemplate::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->whereIn('key', $templateProvisioner->expectedTemplateKeys())
            ->whereIn('channel', $templateProvisioner->supportedChannels())
            ->get(['key', 'channel'])
            ->map(fn (MessageTemplate $template): string => $template->key.'::'.$template->channel)
            ->all();
        $missing = array_values(array_diff($expected, $actual));

        return $this->check(
            $missing === [] ? 'PASS' : 'WARN',
            $missing === []
                ? 'All '.count($expected).' active '.$locale.' notification templates are provisioned.'
                : count($missing).' active '.$locale.' notification template(s) are missing; run php artisan db:seed --class=CapabilitySeeder.',
        );
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function collectionFxCheck(string $baseCurrency, string $collectionCurrency, mixed $now): array
    {
        if ($baseCurrency === $collectionCurrency) {
            return $this->check('PASS', 'Base and collection currencies are the same; no FX rate is required.');
        }

        $hasRate = ExchangeRate::query()
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($baseCurrency, $collectionCurrency): void {
                $query->where(function ($query) use ($baseCurrency, $collectionCurrency): void {
                    $query->where('base_currency', $baseCurrency)->where('quote_currency', $collectionCurrency);
                })->orWhere(function ($query) use ($baseCurrency, $collectionCurrency): void {
                    $query->where('base_currency', $collectionCurrency)->where('quote_currency', $baseCurrency);
                });
            })
            ->exists();

        return $this->check(
            $hasRate ? 'PASS' : 'FAIL',
            $hasRate
                ? 'An effective direct or inverse '.$baseCurrency.'/'.$collectionCurrency.' rate is available.'
                : 'Add an effective '.$baseCurrency.'/'.$collectionCurrency.' rate or run fx:sync-frankfurter.',
        );
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function stripeCheck(): array
    {
        if ((string) config('services.payments.driver', 'null') !== 'stripe') {
            return $this->check('WARN', 'Stripe is not the selected payment driver; cash remains available.');
        }

        $configured = collect(['secret', 'publishable_key', 'endpoint', 'webhook_secret'])
            ->every(fn (string $key): bool => $this->hasConfiguredValue(config('services.stripe.'.$key)));

        return $this->check(
            $configured ? 'PASS' : 'FAIL',
            $configured ? 'Stripe credentials and webhook configuration are present.' : 'Complete Stripe credentials and webhook configuration.',
        );
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function whishCheck(): array
    {
        if (! (bool) config('services.whish.enabled')) {
            return $this->check('WARN', 'Whish Pay is disabled; enable it after merchant and callback acceptance.');
        }

        $configured = collect(['channel', 'secret', 'website_url'])
            ->every(fn (string $key): bool => $this->hasConfiguredValue(config('services.whish.'.$key)));

        return $this->check(
            $configured ? 'PASS' : 'FAIL',
            $configured ? 'Whish Pay credentials and website configuration are present.' : 'Complete Whish Pay merchant credentials and callback configuration.',
        );
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function whatsappCheck(GetWhatsAppSetupStatus $whatsappStatus): array
    {
        $mode = strtolower((string) config('services.whatsapp.mode', 'cloud'));
        if ($mode === 'web') {
            $setup = $whatsappStatus->handle();

            return match ($setup['status']) {
                'ready' => $this->check('PASS', 'The private Web.js bridge is paired and ready.'),
                'qr', 'authenticated', 'starting' => $this->check('WARN', 'The private Web.js bridge is configured but is waiting for account pairing to finish.'),
                'disabled' => $this->check('WARN', 'WhatsApp Web.js is disabled; enable it after pairing a dedicated business account.'),
                'not_configured' => $this->check('FAIL', 'Complete Web.js bridge, token, callback URL and callback secret configuration.'),
                default => $this->check('FAIL', 'The private Web.js bridge is configured but is not ready for delivery.'),
            };
        }

        $configured = $this->hasConfiguredValue(config('services.whatsapp.token'))
            && $this->hasConfiguredValue(config('services.whatsapp.phone_number_id'));

        return $this->check(
            $configured ? 'PASS' : 'WARN',
            $configured ? 'WhatsApp Cloud credentials are configured.' : 'WhatsApp notifications are not configured; configure Cloud API or opt into Web.js.',
        );
    }

    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function tenantLogoCheck(Tenant $tenant): array
    {
        $logoPath = is_string($tenant->logo_path) ? trim($tenant->logo_path) : '';

        if ($logoPath === '') {
            return $this->check('WARN', 'Add a tenant logo before the pilot handoff.');
        }

        $disk = (string) config('filesystems.default', 'local');

        try {
            $available = Storage::disk($disk)->exists($logoPath);
        } catch (Throwable) {
            return $this->check('WARN', 'The configured tenant logo could not be verified on the '.$disk.' storage disk.');
        }

        return $this->check(
            $available ? 'PASS' : 'WARN',
            $available
                ? 'A tenant logo is available on the configured storage disk.'
                : 'The tenant logo path is configured, but the stored file is missing from the '.$disk.' storage disk.',
        );
    }

    /** @param 'PASS'|'WARN'|'FAIL' $status */
    /** @return array{status: 'PASS'|'WARN'|'FAIL', detail: string} */
    private function check(string $status, string $detail): array
    {
        return ['status' => $status, 'detail' => $detail];
    }

    private function hasConfiguredValue(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $normalized = strtolower(trim($value));

        return ! in_array($normalized, ['null', 'replace-me', 'change-me', 'placeholder', 'set-me'], true)
            && ! str_contains($normalized, 'example.');
    }
}
