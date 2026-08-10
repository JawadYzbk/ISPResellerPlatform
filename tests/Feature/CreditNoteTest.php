<?php

use App\Actions\IssueCreditNote;
use App\Actions\IssueInvoice;
use App\Actions\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('issues an append-only credit note and posts the matching ledger reversal', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'credit-notes@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create(['number' => 'INV-CREDIT-001', 'customer_id' => $customer->id, 'status' => InvoiceStatus::Draft, 'currency' => 'USD', 'subtotal_amount' => 3500, 'total_amount' => 3500]);
    app(IssueInvoice::class)->handle($invoice, $user);

    $note = app(IssueCreditNote::class)->handle($invoice, 1000, 'Service interruption', $user);

    app(Tenancy::class)->set($tenant);
    expect($note->number)->toBe('CN-00001')
        ->and($note->status)->toBe('issued')
        ->and($invoice->refresh()->creditNotes()->sum('amount'))->toBe(1000)
        ->and($customer->refresh()->balance_amount)->toBe(2500);

    expect(fn () => app(IssueCreditNote::class)->handle($invoice, 2600, 'Too much', $user))
        ->toThrow(DomainException::class, 'Credit notes cannot exceed the invoice total.');

    expect(fn () => app(VoidInvoice::class)->handle($invoice, $user))
        ->toThrow(DomainException::class, 'An invoice with issued credit notes cannot be voided.');
});

it('shows credit-note capability and issues a note from the invoice page', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'credit-notes-web@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create(['number' => 'INV-CREDIT-WEB', 'customer_id' => $customer->id, 'status' => InvoiceStatus::Issued, 'currency' => 'USD', 'subtotal_amount' => 3500, 'total_amount' => 3500, 'issued_at' => now()]);

    $this->actingAs($user)
        ->get(route('billing.invoices.show', $invoice->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canCredit', true)->where('invoice.credited_amount', 0));

    $this->actingAs($user)
        ->post(route('billing.invoices.credit-notes', $invoice->public_id), ['amount' => 1000, 'reason' => 'Service interruption'])
        ->assertRedirect(route('billing.invoices.show', $invoice->public_id));

    app(Tenancy::class)->set($tenant);
    expect($invoice->refresh()->creditNotes()->count())->toBe(1);
});
