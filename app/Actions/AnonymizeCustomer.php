<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AnonymizeCustomer implements Action
{
    public function handle(Customer $customer, User $actor): Customer
    {
        return DB::transaction(function () use ($customer, $actor): Customer {
            $locked = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            if ($locked->anonymized_at !== null) {
                return $locked;
            }

            $publicId = (string) $locked->public_id;
            $locked->forceFill([
                'code' => 'ANON-'.$publicId,
                'first_name' => 'Anonymized',
                'last_name' => 'Customer',
                'phone' => 'ANON-'.$publicId,
                'phone_normalized' => 'anon'.$publicId,
                'email' => null,
                'address' => null,
                'latitude' => null,
                'longitude' => null,
                'notes' => null,
                'notification_preferences' => null,
                'anonymized_at' => now(),
            ]);

            Customer::withoutEvents(function () use ($locked): void {
                $locked->save();
            });

            activity('security')
                ->causedBy($actor)
                ->performedOn($locked)
                ->withProperties(['action' => 'customer.anonymized', 'customer_public_id' => $publicId])
                ->log('Customer anonymized');

            return $locked->refresh();
        });
    }
}
