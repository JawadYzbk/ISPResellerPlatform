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

    /** @param array<string, mixed> $priceContext */
    public function handle(Customer $customer, Plan $plan, ?Service $service = null, ?CarbonImmutable $at = null, int $quantity = 1, ?int $unitAmount = null, ?string $description = null, array $priceContext = []): Invoice
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

        $unitAmount ??= $price->amount_minor;
        if ($unitAmount < 0) {
            throw new DomainException('Invoice unit amounts cannot be negative.');
        }

        return DB::transaction(function () use ($customer, $plan, $service, $price, $quantity, $unitAmount, $description, $priceContext): Invoice {
            $total = $unitAmount * $quantity;
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
                'description' => $description ?? $plan->name,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'total_amount' => $total,
                'currency' => $price->currency,
                'price_snapshot' => [
                    'amount_minor' => $price->amount_minor,
                    'billed_unit_amount' => $unitAmount,
                    'currency' => $price->currency,
                    'effective_from' => $price->effective_from->toIso8601String(),
                    ...$priceContext,
                ],
            ]);

            return $invoice->load('lines');
        });
    }
}
