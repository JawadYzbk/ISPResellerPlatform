<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePortalProfile implements Action
{
    /** @param array<string, mixed> $attributes */
    public function handle(Customer $customer, array $attributes): Customer
    {
        return DB::transaction(function () use ($customer, $attributes): Customer {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $locked->forceFill($attributes)->save();

            return $locked->refresh();
        });
    }
}
