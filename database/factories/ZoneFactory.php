<?php

namespace Database\Factories;

use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Zone> */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('ZN-???'));

        return [
            'tenant_id' => app(Tenancy::class)->id(),
            'name' => fake()->city(),
            'code' => $code,
        ];
    }
}
