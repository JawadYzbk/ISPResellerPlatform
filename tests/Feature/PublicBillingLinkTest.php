<?php

use App\Actions\CreatePublicBillingLink;
use App\Actions\ResolvePublicBillingLink;
use App\Actions\RevokePublicBillingLink;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentIntentResult;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PublicBillingLink;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, Customer, Invoice, Payment} */
function publicBillingWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Public Billing ISP', 'slug' => 'public-billing']);
    app(Tenancy::class)->set($tenant);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Billing Owner',
        'email' => 'public-billing@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create([
        'number' => 'INV-PUBLIC-001',
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'currency' => 'USD',
        'subtotal_amount' => 5000,
        'tax_amount' => 0,
        'total_amount' => 5000,
        'issued_at' => now(),
        'due_at' => now()->addWeek(),
    ]);
    $payment = Payment::create([
        'number' => 'RCT-PUBLIC-001',
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'status' => PaymentStatus::Posted,
        'amount' => 2000,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'public-billing-payment',
        'received_at' => now(),
        'actor_id' => $owner->id,
    ]);

    return [$tenant, $owner->refresh(), $customer, $invoice, $payment];
}

it('stores only a hash and resolves an active public billing token across tenant scope', function (): void {
    [$tenant, $owner, $customer, $invoice] = publicBillingWorkspace();
    $created = app(CreatePublicBillingLink::class)->handle($owner, 'payment', $customer, $invoice, null, 7);

    expect($created->token)->toHaveLength(64)
        ->and($created->link->token_hash)->toBe(hash('sha256', $created->token))
        ->and($created->link->token_hash)->not->toBe($created->token);

    app(Tenancy::class)->clear();
    $resolved = app(ResolvePublicBillingLink::class)->handle($created->token);

    expect($resolved->tenant_id)->toBe($tenant->id)
        ->and($resolved->invoice?->number)->toBe('INV-PUBLIC-001')
        ->and($resolved->access_count)->toBe(1)
        ->and($resolved->last_accessed_at)->not->toBeNull();
});

it('fails closed for expired, revoked, malformed, and cross-customer targets', function (): void {
    [, $owner, $customer, $invoice, $payment] = publicBillingWorkspace();
    $created = app(CreatePublicBillingLink::class)->handle($owner, 'receipt', $customer, null, $payment, 2);
    app(RevokePublicBillingLink::class)->handle($owner, $created->link);
    app(Tenancy::class)->clear();

    expect(fn () => app(ResolvePublicBillingLink::class)->handle($created->token))
        ->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(ResolvePublicBillingLink::class)->handle('not-a-token'))
        ->toThrow(NotFoundHttpException::class);

    app(Tenancy::class)->set($customer->tenant);
    $otherCustomer = Customer::factory()->create();
    expect(fn () => app(CreatePublicBillingLink::class)->handle($owner, 'payment', $otherCustomer, $invoice, null, 7))
        ->toThrow(DomainException::class, 'Choose an invoice belonging to this customer.');

    $expired = app(CreatePublicBillingLink::class)->handle($owner, 'invoice', $customer, $invoice, null, 1);
    $expired->link->forceFill(['expires_at' => now()->subMinute()])->save();
    app(Tenancy::class)->clear();
    expect(fn () => app(ResolvePublicBillingLink::class)->handle($expired->token))
        ->toThrow(NotFoundHttpException::class)
        ->and(PublicBillingLink::withoutGlobalScopes()->count())->toBe(2);
});

it('creates a one-time visible staff link and serves branded public invoice checkout', function (): void {
    [$tenant, $owner, , $invoice] = publicBillingWorkspace();
    $response = $this->actingAs($owner)->post(route('billing.invoices.public-links.store', $invoice), [
        'type' => 'payment',
        'expires_in_days' => 7,
    ]);
    $response->assertRedirect()->assertSessionHas('success', 'Public billing link created. Copy it before leaving this page.');
    $payload = $response->getSession()->get('publicLink');
    expect($payload)->toBeArray()->and($payload['url'] ?? null)->toBeString();
    $token = basename((string) parse_url((string) $payload['url'], PHP_URL_PATH));

    app(Tenancy::class)->clear();
    $this->get(route('public.billing.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Billing')
            ->where('tenant.name', $tenant->name)
            ->where('type', 'payment')
            ->where('invoice.number', 'INV-PUBLIC-001')
            ->where('invoice.outstanding_amount', 5000));

    app(Tenancy::class)->clear();
    $this->get(route('public.billing.pdf', $token))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function createIntent(Customer $customer, Invoice $invoice, int $amount, string $currency, string $idempotencyKey): PaymentIntentResult
        {
            return new PaymentIntentResult('pi_public_001', 'requires_action', $amount, $currency, [
                'client_secret' => 'pi_public_secret',
                'publishable_key' => 'pk_test_public',
            ]);
        }
    });
    app(Tenancy::class)->clear();
    $this->postJson(route('api.public-billing.stripe', $token), ['amount' => 5000], ['X-Idempotency-Key' => 'public-link-stripe-001'])
        ->assertCreated()
        ->assertJsonPath('id', 'pi_public_001')
        ->assertJsonPath('amount', 5000);

    app(Tenancy::class)->clear();
    $this->postJson(route('api.public-billing.whish', $token), ['amount' => 5001], ['X-Idempotency-Key' => 'public-link-whish-overpay'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The payment amount exceeds the invoice balance.');
    expect(PaymentAttempt::withoutGlobalScopes()->count())->toBe(0);
});
