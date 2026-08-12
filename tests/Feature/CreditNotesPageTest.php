<?php

use App\Actions\IssueCreditNote;
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

it('lists tenant credit notes with invoice and customer context', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'credit-notes-index@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create([
        'number' => 'INV-CREDIT-INDEX',
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'currency' => 'USD',
        'subtotal_amount' => 3500,
        'total_amount' => 3500,
        'issued_at' => now(),
    ]);
    $note = app(IssueCreditNote::class)->handle($invoice, 1000, 'Service interruption', $user);

    $this->actingAs($user)
        ->get(route('billing.credit-notes', ['search' => $note->number]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/CreditNotes')
            ->where('creditNotes.data.0.number', $note->number)
            ->where('creditNotes.data.0.amount', 1000)
            ->where('creditNotes.data.0.invoice.number', $invoice->number)
            ->where('creditNotes.data.0.customer.code', $customer->code)
            ->where('filters.search', $note->number)
        );
});
