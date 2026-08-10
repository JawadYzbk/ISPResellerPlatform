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

    public function handle(Customer $customer, Plan $plan, ?Service $service = null, ?CarbonImmutable $at = null, int $quantity = 1): Invoice
    {
        if ($quantity < 1) {
            throw new DomainException('Invoice quantity must be positive.');
        }
        if ($customer->tenant_id !== $plan->tenant_id || ($service !== null && $service->customer_id !== $customer->id)) {
            throw new DomainException('Invoice records must belong to the same customer and tenant.');
        }

        $at ??= CarbonImmutable::now();
        $price = $plan->priceAt($at);
        if ($price === null) {
            throw new DomainException('The plan has no effective price at the invoice date.');
        }

        return DB::transaction(function () use ($customer, $plan, $service, $price, $quantity): Invoice {
            $total = $price->amount_minor * $quantity;
            $invoice = Invoice::create([
                'number' => $this->numbers->next('invoice', 'INV'),
                'customer_id' => $customer->id,
                'status' => 'draft',
                'currency' => $price->currency,
                'subtotal_amount' => $total,
                'tax_amount' => 0,
                'total_amount' => $total,
            ]);
            $invoice->lines()->create([
                'plan_id' => $plan->id,
                'service_id' => $service?->id,
                'description' => $plan->name,
                'quantity' => $quantity,
                'unit_amount' => $price->amount_minor,
                'total_amount' => $total,
                'currency' => $price->currency,
                'price_snapshot' => ['amount_minor' => $price->amount_minor, 'currency' => $price->currency, 'effective_from' => $price->effective_from->toIso8601String()],
            ]);

            return $invoice->load('lines');
        });
    }
}
