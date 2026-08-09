<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Service;
use App\Support\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateInvoice implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function handle(Customer $customer, Plan $plan, ?Service $service = null, ?CarbonImmutable $at = null): Invoice
    {
        if ($customer->tenant_id !== $plan->tenant_id || ($service !== null && $service->customer_id !== $customer->id)) {
            throw new DomainException('Invoice records must belong to the same customer and tenant.');
        }

        $at ??= CarbonImmutable::now();
        $price = $plan->priceAt($at);
        if ($price === null) {
            throw new DomainException('The plan has no effective price at the invoice date.');
        }

        return DB::transaction(function () use ($customer, $plan, $service, $price): Invoice {
            $invoice = Invoice::create([
                'number' => $this->numbers->next('invoice', 'INV'),
                'customer_id' => $customer->id,
                'status' => 'draft',
                'currency' => $price->currency,
                'subtotal_amount' => $price->amount_minor,
                'tax_amount' => 0,
                'total_amount' => $price->amount_minor,
            ]);
            $invoice->lines()->create([
                'plan_id' => $plan->id,
                'service_id' => $service?->id,
                'description' => $plan->name,
                'quantity' => 1,
                'unit_amount' => $price->amount_minor,
                'total_amount' => $price->amount_minor,
                'currency' => $price->currency,
                'price_snapshot' => ['amount_minor' => $price->amount_minor, 'currency' => $price->currency, 'effective_from' => $price->effective_from->toIso8601String()],
            ]);

            return $invoice->load('lines');
        });
    }
}
