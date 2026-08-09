<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\NetworkState;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(['slug' => 'northline'], ['name' => 'Northline Broadband', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'timezone' => 'Asia/Beirut', 'locale' => 'en']);
        $admin = User::updateOrCreate(['email' => 'admin@example.com'], ['tenant_id' => $tenant->id, 'name' => 'Maya Haddad', 'password' => Hash::make('password'), 'role' => 'tenant_owner', 'locale' => 'en', 'email_verified_at' => now()]);

        $this->call(CapabilitySeeder::class);
        app(Tenancy::class)->run($tenant, fn (): mixed => $admin->assignRole('tenant_owner'));

        app(Tenancy::class)->run($tenant, function (): void {
            $zones = collect([
                ['name' => 'Central District', 'code' => 'CENTRAL'],
                ['name' => 'Hillside', 'code' => 'HILL'],
                ['name' => 'Coastal Road', 'code' => 'COAST'],
            ])->mapWithKeys(function (array $data): array {
                $zone = Zone::updateOrCreate(['code' => $data['code']], $data);

                return [$data['code'] => $zone];
            });
            Branch::updateOrCreate(['code' => 'HQ'], ['name' => 'Main Office', 'is_default' => true, 'address' => '12 Cedar Street']);

            $plans = collect([
                ['name' => 'Home 25', 'slug' => 'home-25', 'download_kbps' => 25_000, 'upload_kbps' => 5_000, 'duration_days' => 30, 'amount_minor' => 2500, 'currency' => 'USD'],
                ['name' => 'Home 50', 'slug' => 'home-50', 'download_kbps' => 50_000, 'upload_kbps' => 10_000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD'],
                ['name' => 'Business 100', 'slug' => 'business-100', 'download_kbps' => 100_000, 'upload_kbps' => 25_000, 'duration_days' => 30, 'amount_minor' => 6500, 'currency' => 'USD'],
                ['name' => 'Starter 10', 'slug' => 'starter-10', 'download_kbps' => 10_000, 'upload_kbps' => 2_000, 'duration_days' => 30, 'amount_minor' => 1800, 'currency' => 'USD'],
            ])->mapWithKeys(function (array $data): array {
                $plan = Plan::updateOrCreate(['slug' => $data['slug']], $data);
                PlanPrice::firstOrCreate(
                    ['plan_id' => $plan->id, 'currency' => $data['currency'], 'effective_from' => now()->startOfDay()],
                    ['amount_minor' => $data['amount_minor']],
                );

                return [$data['slug'] => $plan];
            });

            $customers = [
                ['first_name' => 'Rami', 'last_name' => 'Saad', 'phone' => '+961 70 123 456', 'email' => 'rami@example.test', 'zone' => 'CENTRAL', 'plan' => 'home-50', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(18)],
                ['first_name' => 'Lina', 'last_name' => 'Khoury', 'phone' => '+961 71 234 567', 'email' => 'lina@example.test', 'zone' => 'HILL', 'plan' => 'business-100', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(4)],
                ['first_name' => 'Omar', 'last_name' => 'Nasser', 'phone' => '+961 76 345 678', 'email' => null, 'zone' => 'COAST', 'plan' => 'starter-10', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Suspended, 'expires' => now()->subDays(3)],
                ['first_name' => 'Nour', 'last_name' => 'Mansour', 'phone' => '+961 70 456 789', 'email' => 'nour@example.test', 'zone' => 'CENTRAL', 'plan' => 'home-25', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Pending, 'expires' => now()->addDays(1)],
                ['first_name' => 'Tarek', 'last_name' => 'Fadel', 'phone' => '+961 70 567 890', 'email' => null, 'zone' => 'HILL', 'plan' => 'home-50', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(22)],
                ['first_name' => 'Sara', 'last_name' => 'Haddad', 'phone' => '+961 71 678 901', 'email' => 'sara@example.test', 'zone' => 'COAST', 'plan' => 'home-25', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(9)],
            ];

            foreach ($customers as $index => $data) {
                $customer = Customer::updateOrCreate(['code' => 'CUS-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)], ['zone_id' => $zones[$data['zone']]->id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'], 'phone_normalized' => preg_replace('/\D+/', '', $data['phone']), 'email' => $data['email'], 'address' => ($index + 10).' Cedar Street', 'status' => $data['status'], 'balance_amount' => $data['service_status'] === ServiceStatus::Suspended ? 3500 : 0, 'balance_currency' => 'USD']);
                Service::updateOrCreate(['username' => strtolower($data['first_name']).'.'.($index + 1)], ['customer_id' => $customer->id, 'plan_id' => $plans[$data['plan']]->id, 'password_encrypted' => 'demo-secret-not-for-production', 'status' => $data['service_status'], 'provisioning_mode' => ProvisioningMode::Manual, 'network_state' => $data['service_status'] === ServiceStatus::Active ? NetworkState::InSync : NetworkState::PendingSync, 'activated_at' => now()->subMonths(4), 'expires_at' => $data['expires']]);
            }
        });
    }
}
