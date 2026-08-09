<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => app(Tenancy::class)->id(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'download_kbps' => fake()->randomElement([10_000, 25_000, 50_000, 100_000]),
            'upload_kbps' => fake()->randomElement([2_000, 5_000, 10_000, 25_000]),
            'duration_days' => 30,
            'amount_minor' => fake()->numberBetween(1_000, 20_000),
            'currency' => 'USD',
        ];
    }
}
