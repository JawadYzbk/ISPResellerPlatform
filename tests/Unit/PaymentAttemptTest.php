<?php

use App\Enums\PaymentAttemptStatus;
use App\Models\Customer;
use App\Models\PaymentAttempt;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores tenant-scoped payment attempts with their provider lifecycle status', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();

    $attempt = PaymentAttempt::create([
        'gateway' => 'whish',
        'external_id' => '123456789',
        'customer_id' => $customer->id,
        'amount' => 1250,
        'currency' => 'USD',
        'status' => PaymentAttemptStatus::Pending,
        'idempotency_key' => 'whish:123456789',
        'invoice_reference' => 'INV-100',
    ]);

    expect($attempt->public_id)->not->toBeEmpty()
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Pending)
        ->and(PaymentAttempt::query()->count())->toBe(1);
});
