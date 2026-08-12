<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateManualInvoice implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function handle(
        Customer $customer,
        string $description,
        int $amount,
        string $currency,
        ?CarbonImmutable $dueAt = null,
    ): Invoice {
        $description = trim($description);
        $currency = strtoupper(trim($currency));

        if ($description === '') {
            throw new DomainException('Invoice description is required.');
        }
        if ($amount < 1) {
            throw new DomainException('Invoice amount must be positive.');
        }

        $currencyRecord = Currency::query()
            ->where('code', $currency)
            ->where('is_active', true)
            ->first();
        if ($currencyRecord === null) {
            throw new DomainException('Choose an active workspace currency.');
        }

        return DB::transaction(function () use ($customer, $description, $amount, $currency, $currencyRecord, $dueAt): Invoice {
            $invoice = Invoice::create([
                'number' => $this->numbers->next('invoice', 'INV'),
                'customer_id' => $customer->id,
                'status' => 'draft',
                'currency' => $currency,
                'subtotal_amount' => $amount,
                'tax_amount' => 0,
                'total_amount' => $amount,
                'due_at' => $dueAt,
                'metadata' => [
                    'source' => 'manual',
                    'decimal_digits' => $currencyRecord->decimal_digits,
                ],
            ]);

            $invoice->lines()->create([
                'description' => $description,
                'quantity' => 1,
                'unit_amount' => $amount,
                'total_amount' => $amount,
                'currency' => $currency,
                'price_snapshot' => [
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'decimal_digits' => $currencyRecord->decimal_digits,
                    'source' => 'operator',
                ],
            ]);

            return $invoice->load('lines');
        });
    }
}
