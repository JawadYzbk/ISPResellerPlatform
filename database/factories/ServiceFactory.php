<?php

namespace Database\Factories;

use App\Enums\NetworkState;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'tenant_id' => app(Tenancy::class)->id(),
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'username' => fake()->unique()->userName(),
            'password_encrypted' => fake()->password(),
            'status' => ServiceStatus::Pending,
            'provisioning_mode' => ProvisioningMode::Manual,
            'network_state' => NetworkState::Unknown,
            'expires_at' => now()->addDays(30),
        ];
    }
}
