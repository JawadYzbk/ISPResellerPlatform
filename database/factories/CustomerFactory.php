<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = '+961 70 '.fake()->unique()->numerify('### ###');

        return [
            'tenant_id' => app(Tenancy::class)->id(),
            'code' => 'CUS-'.strtoupper(Str::random(8)),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => $phone,
            'phone_normalized' => preg_replace('/\D+/', '', $phone),
            'email' => fake()->safeEmail(),
            'status' => CustomerStatus::Active,
            'balance_currency' => 'USD',
        ];
    }
}
