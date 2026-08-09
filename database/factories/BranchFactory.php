<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('BR-???'));

        return [
            'tenant_id' => app(Tenancy::class)->id(),
            'name' => fake()->company().' Branch',
            'code' => $code,
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
