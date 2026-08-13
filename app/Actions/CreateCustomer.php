<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CustomerStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CustomerCodeGenerator;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;

final readonly class CreateCustomer implements Action
{
    public function __construct(
        private CustomerCodeGenerator $codeGenerator,
        private DocumentNumberGenerator $numbers,
        private CreateService $createService,
        private QueueCustomerNotification $notify,
    ) {}

    public function handle(CustomerRequest $request, Tenant $tenant, ?User $actor = null): Customer
    {
        $data = $request->validated();
        $customer = DB::transaction(function () use ($data, $tenant, $actor): Customer {
            $customer = Customer::create([
                ...array_intersect_key($data, array_flip(['zone_id', 'first_name', 'last_name', 'phone', 'email', 'address', 'latitude', 'longitude'])),
                'code' => $this->codeGenerator->next(),
                'status' => CustomerStatus::Active,
                'balance_currency' => $tenant->collection_currency,
            ]);

            if (($data['create_service'] ?? false) === true) {
                $service = $this->createService->handle($customer, array_intersect_key($data, array_flip(['plan_id', 'username', 'password', 'provisioning_mode', 'router_id', 'billing_anchor_day'])), $actor);
                $workOrder = WorkOrder::create([
                    'number' => $this->numbers->next('work_order', 'WO'),
                    'type' => 'installation',
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'status' => WorkOrderStatus::Pending,
                    'checklist' => [],
                    'metadata' => [
                        'source' => 'subscriber_registration',
                        'requires_installation_acceptance' => true,
                    ],
                ]);
                $workOrder->events()->create([
                    'actor_id' => $actor?->id,
                    'event_type' => 'created',
                    'to_status' => WorkOrderStatus::Pending->value,
                    'metadata' => ['source' => 'subscriber_registration'],
                ]);
            }

            return $customer;
        });

        $this->notify->handle($customer, 'customer.welcome', 'customer-welcome:'.$customer->id, [
            'customer_name' => $customer->full_name,
            'customer_code' => $customer->code,
        ]);

        return $customer;
    }
}
