<?php

use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records tenant model changes with the changed properties', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    app(Tenancy::class)->run($tenant, function (): void {
        $customer = Customer::create([
            'code' => 'CUS-00001',
            'first_name' => 'Rami',
            'last_name' => 'Saad',
            'phone' => '+961 70 123 456',
            'phone_normalized' => '96170123456',
            'balance_currency' => 'USD',
        ]);

        $customer->update(['first_name' => 'Rami Updated']);
    });

    app(Tenancy::class)->set($tenant);
    $events = AuditEvent::query()->where('subject_type', Customer::class)->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events->first()->tenant_id)->toBe($tenant->id)
        ->and($events->last()->properties->get('attributes')['first_name'])->toBe('Rami Updated');
});

it('cannot read another tenant audit events', function (): void {
    $north = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $south = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    app(Tenancy::class)->run($north, fn (): Customer => Customer::create([
        'code' => 'CUS-00001', 'first_name' => 'North', 'phone' => '+961 70 111 111', 'phone_normalized' => '96170111111', 'balance_currency' => 'USD',
    ]));

    app(Tenancy::class)->set($south);

    expect(AuditEvent::query()->where('subject_type', Customer::class)->count())->toBe(0);
});
