<?php

use App\Domain\Network\SubscriberReader;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('imports RouterOS subscribers into a redacted discovery report', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'router-secret']);
    app()->instance(SubscriberReader::class, new class implements SubscriberReader
    {
        public function read(Router $router): array
        {
            return [
                ['name' => 'ada.home', 'comment' => 'legacy subscriber', 'password' => 'secret-value', 'disabled' => 'false', 'profile' => 'plan-1'],
                ['comment' => 'missing name', 'password' => 'another-secret'],
            ];
        }
    });
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'router-import@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $token = $user->createToken('router-importer', ['api', 'staff:operator'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/imports/routers/'.$router->id.'/subscribers');

    $response->assertCreated()
        ->assertJsonPath('type', 'router_subscribers')
        ->assertJsonPath('successful_rows', 1)
        ->assertJsonPath('failed_rows', 1)
        ->assertJsonPath('report.0.data.username', 'ada.home')
        ->assertJsonMissing(['password' => 'secret-value'])
        ->assertJsonMissing(['password' => 'another-secret']);
});
