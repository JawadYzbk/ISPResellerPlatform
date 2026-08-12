<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CredentialStatus;
use App\Enums\NetworkState;
use App\Enums\PaymentStatus;
use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\PartnerWallet;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetDashboardAttentionQueue implements Action
{
    /**
     * @return list<array{type: string, title: string, detail: string, href: string, severity: string}>
     */
    public function handle(int $perType = 5, ?User $user = null): array
    {
        $limit = min(max($perType, 1), 10);
        $tenant = Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
        $rows = [];

        Service::query()
            ->with('customer')
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use (&$rows): void {
                $this->add($rows, 'expired_service', 'Expired active service', $service->username.' · '.$this->customerName($service->customer), '/services?search='.urlencode($service->username), 'critical');
            });

        Service::query()
            ->with('customer')
            ->whereIn('status', [ServiceStatus::Pending, ServiceStatus::Active])
            ->where('network_state', NetworkState::Failed)
            ->whereHas('invoiceLines.invoice.payments', fn (Builder $query) => $query->where('payments.status', PaymentStatus::Posted->value))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use (&$rows): void {
                $this->add($rows, 'paid_provisioning_failed', 'Paid service failed provisioning', $service->username.' · '.$this->customerName($service->customer), '/services?search='.urlencode($service->username), 'critical');
            });

        Payment::query()
            ->with('customer')
            ->where('status', PaymentStatus::Posted)
            ->doesntHave('allocations')
            ->orderBy('received_at')
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use (&$rows): void {
                $this->add($rows, 'unallocated_payment', 'Unallocated payment', $payment->number.' · '.$this->customerName($payment->customer), '/customers/'.$payment->customer->public_id, 'warning');
            });

        $staleSeconds = max(1, (int) ($tenant->settingsData()->settings['radius_interim_interval_seconds'] ?? 300)) * 2;
        CurrentSession::query()
            ->with('service')
            ->whereNull('stopped_at')
            ->where('last_seen_at', '<', now()->subSeconds($staleSeconds))
            ->orderBy('last_seen_at')
            ->limit($limit)
            ->get()
            ->each(function (CurrentSession $session) use (&$rows): void {
                $username = $session->service->username;
                $this->add($rows, 'stale_session', 'Stale live session', $username.' · last seen '.($session->last_seen_at?->diffForHumans() ?? 'unknown'), '/services?search='.urlencode($username), 'warning');
            });

        PartnerWallet::query()
            ->with('partner')
            ->whereHas('partner', fn (Builder $query) => $query->whereColumn('partner_wallets.balance_amount', '<=', 'partners.low_balance_threshold'))
            ->orderBy('balance_amount')
            ->limit($limit)
            ->get()
            ->each(function (PartnerWallet $wallet) use (&$rows): void {
                $partner = $wallet->partner;
                $this->add($rows, 'low_reseller_balance', 'Low reseller balance', $partner->name.' · '.$wallet->balance_amount.' '.$wallet->currency, '/partners/commercial?partner='.urlencode($partner->public_id), 'warning');
            });

        UpstreamCredential::query()
            ->with('batch.supplier')
            ->whereNotIn('status', [CredentialStatus::Expired, CredentialStatus::Revoked])
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (UpstreamCredential $credential) use (&$rows): void {
                $supplier = $credential->batch?->supplier?->name;
                $detail = $credential->identifier.($supplier === null ? '' : ' · '.$supplier);
                $this->add($rows, 'expiring_supplier_credential', 'Expiring supplier credential', $detail, '/operations/credentials?search='.urlencode($credential->identifier), 'warning');
            });

        if ($user === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => match ($row['type']) {
                'expired_service', 'paid_provisioning_failed', 'stale_session' => $user->can('services.view'),
                'unallocated_payment' => $user->can('payments.collect') || $user->can('billing.invoices.view'),
                'low_reseller_balance' => $user->can('wallets.view'),
                'expiring_supplier_credential' => $user->can('suppliers.view'),
                default => false,
            },
        ));
    }

    /** @param list<array{type: string, title: string, detail: string, href: string, severity: string}> $rows */
    private function add(array &$rows, string $type, string $title, string $detail, string $href, string $severity): void
    {
        $rows[] = compact('type', 'title', 'detail', 'href', 'severity');
    }

    private function customerName(Customer $customer): string
    {
        return $customer->full_name;
    }
}
